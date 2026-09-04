<?php

use App\Enums\CaseStatus;
use App\Enums\LandlordContactRole;
use App\Models\CaseEvent;
use App\Models\Property;
use App\Models\PropertyLandlordContact;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

/**
 * Every editable field, changed one at a time, then several at once.
 *
 * The reason this exists: the postal-address defect was found by hand,
 * by changing a field nobody had thought to change. Two rules govern
 * every field and only one field is special, so the honest way to hold
 * that is a matrix rather than a handful of chosen examples.
 *
 *   - ANY changed field creates a new version. The contact record
 *     changed and the history must show it.
 *   - Only a changed EMAIL writes a correction event on the open cases,
 *     because only the email decides where a letter goes.
 */

/** A baseline with EVERY field populated, so a change to any one shows. */
function fullContact(): array
{
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    $property->setLandlordContact([
        'email' => 'base@example.com',
        'name' => 'Base Name',
        'role' => LandlordContactRole::Landlord,
        'organisation_name' => 'Base Agency',
        'address_line1' => '1 Base Street',
        'address_line2' => 'Base Flat',
        'city' => 'Basingstoke',
        'postcode' => 'RG1 5SE',
    ], now(), $tenant->id);

    $case = RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);

    return [$tenant, $property->fresh(), $case];
}

/**
 * The form posts every field on every submit, so the payload here is the
 * current state with overrides applied — exactly what the browser sends.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payloadFrom(PropertyLandlordContact $c, array $overrides = []): array
{
    return array_merge([
        'email' => $c->email,
        'name' => $c->name,
        'role' => $c->role->value,
        'organisation_name' => $c->organisation_name,
        'address_line1' => $c->address_line1,
        'address_line2' => $c->address_line2,
        'city' => $c->city,
        'postcode' => $c->postcode,
    ], $overrides);
}

/** field => the new value to set. Covers every editable column. */
dataset('every field', [
    'email' => ['email', 'moved@example.com'],
    'name' => ['name', 'Changed Name'],
    'role' => ['role', 'agent'],
    'organisation_name' => ['organisation_name', 'Changed Agency'],
    'address_line1' => ['address_line1', '95 Crescent Road'],
    'address_line2' => ['address_line2', 'Changed Flat'],
    'city' => ['city', 'Reading'],
    'postcode' => ['postcode', 'M1 1AA'],
]);

/*
 * Rule 1 — any changed field creates a version.
 */

it('creates a new version when [field] alone changes', function (string $field, string $value) {
    [$tenant, $property] = fullContact();
    $before = $property->currentLandlordContact;

    $this->actingAs($tenant)->patch(
        route('properties.contact.update', $property),
        payloadFrom($before, [$field => $value]),
    );

    $property->refresh();

    expect($property->landlordContacts()->count())->toBe(2)
        ->and($property->currentLandlordContact->id)->not->toBe($before->id)
        ->and($before->fresh()->superseded_at)->not->toBeNull()
        ->and($before->fresh()->is_current)->toBeNull();
})->with('every field');

it('applies the change to [field] and carries every other field forward', function (string $field, string $value) {
    [$tenant, $property] = fullContact();
    $before = $property->currentLandlordContact;

    $this->actingAs($tenant)->patch(
        route('properties.contact.update', $property),
        payloadFrom($before, [$field => $value]),
    );

    $after = $property->fresh()->currentLandlordContact;

    // The changed field took the new value...
    $actual = $field === 'role' ? $after->role->value : $after->$field;
    expect($actual)->toBe($value);

    // ...and nothing else moved with it.
    foreach (['email', 'name', 'organisation_name', 'address_line1', 'address_line2', 'city', 'postcode'] as $other) {
        if ($other === $field) {
            continue;
        }
        expect($after->$other)->toBe($before->$other);
    }

    if ($field !== 'role') {
        expect($after->role->value)->toBe($before->role->value);
    }
})->with('every field');

it('leaves the superseded version untouched when [field] changes', function (string $field, string $value) {
    [$tenant, $property] = fullContact();
    $before = $property->currentLandlordContact;
    $snapshot = $before->only(['email', 'name', 'organisation_name', 'address_line1', 'address_line2', 'city', 'postcode']);

    $this->actingAs($tenant)->patch(
        route('properties.contact.update', $property),
        payloadFrom($before, [$field => $value]),
    );

    expect($before->fresh()->only(array_keys($snapshot)))->toBe($snapshot);
})->with('every field');

/*
 * Rule 2 — only the email is a correction.
 */

it('writes a correction event ONLY when the email changes', function (string $field, string $value) {
    [$tenant, $property, $case] = fullContact();
    $before = $property->currentLandlordContact;

    $this->actingAs($tenant)->patch(
        route('properties.contact.update', $property),
        payloadFrom($before, [$field => $value]),
    );

    $events = CaseEvent::where('case_id', $case->id)
        ->where('event_type', 'landlord_contact_corrected')
        ->get();

    if ($field === 'email') {
        expect($events)->toHaveCount(1)
            ->and($events->first()->meta)->toBe(['from' => 'base@example.com', 'to' => 'moved@example.com']);
    } else {
        expect($events)->toBeEmpty();
    }
})->with('every field');

it('promises a redirection ONLY when the email changes', function (string $field, string $value) {
    [$tenant, $property] = fullContact();
    $before = $property->currentLandlordContact;

    $response = $this->actingAs($tenant)->patch(
        route('properties.contact.update', $property),
        payloadFrom($before, [$field => $value]),
    );

    $response->assertSessionHas('success', fn ($m) => $field === 'email'
        ? str_contains($m, 'new address')
        : ! str_contains($m, 'new address'));
})->with('every field');

/*
 * Several at once.
 */

it('treats a change to every field at once as ONE version', function () {
    [$tenant, $property, $case] = fullContact();
    $before = $property->currentLandlordContact;

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), payloadFrom($before, [
        'email' => 'moved@example.com',
        'name' => 'Changed Name',
        'role' => 'agent',
        'organisation_name' => 'Changed Agency',
        'address_line1' => '95 Crescent Road',
        'address_line2' => 'Changed Flat',
        'city' => 'Reading',
        'postcode' => 'M1 1AA',
    ]));

    $after = $property->fresh()->currentLandlordContact;

    expect($property->fresh()->landlordContacts()->count())->toBe(2)
        ->and($after->email)->toBe('moved@example.com')
        ->and($after->name)->toBe('Changed Name')
        ->and($after->role->value)->toBe('agent')
        ->and($after->organisation_name)->toBe('Changed Agency')
        ->and($after->address_line1)->toBe('95 Crescent Road')
        ->and($after->address_line2)->toBe('Changed Flat')
        ->and($after->city)->toBe('Reading')
        ->and($after->postcode)->toBe('M1 1AA')
        // One event for the whole change, not one per field.
        ->and(CaseEvent::where('case_id', $case->id)
            ->where('event_type', 'landlord_contact_corrected')->count())->toBe(1);
});

it('writes no correction event when several NON-email fields change together', function () {
    [$tenant, $property, $case] = fullContact();
    $before = $property->currentLandlordContact;

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), payloadFrom($before, [
        'name' => 'Changed Name',
        'organisation_name' => 'Changed Agency',
        'address_line1' => '95 Crescent Road',
        'city' => 'Reading',
    ]));

    expect($property->fresh()->landlordContacts()->count())->toBe(2)
        ->and(CaseEvent::where('case_id', $case->id)
            ->where('event_type', 'landlord_contact_corrected')->count())->toBe(0);
});

it('creates no version at all when every field is resubmitted unchanged', function () {
    [$tenant, $property, $case] = fullContact();

    $this->actingAs($tenant)->patch(
        route('properties.contact.update', $property),
        payloadFrom($property->currentLandlordContact),
    );

    expect($property->fresh()->landlordContacts()->count())->toBe(1)
        ->and(CaseEvent::where('case_id', $case->id)
            ->where('event_type', 'landlord_contact_corrected')->count())->toBe(0);
});

/*
 * Clearing a field is a change too — the one direction an "is it
 * different" check is easiest to get wrong, because null and '' and
 * absent all look similar and only one of them is what the form sends.
 */

it('treats clearing [field] as a change', function (string $field) {
    [$tenant, $property] = fullContact();
    $before = $property->currentLandlordContact;

    $this->actingAs($tenant)->patch(
        route('properties.contact.update', $property),
        payloadFrom($before, [$field => '']),
    );

    $after = $property->fresh()->currentLandlordContact;

    expect($property->fresh()->landlordContacts()->count())->toBe(2)
        ->and($after->$field)->toBeNull();
})->with([
    'name' => ['name'],
    'organisation_name' => ['organisation_name'],
    'address_line1' => ['address_line1'],
    'address_line2' => ['address_line2'],
    'city' => ['city'],
    'postcode' => ['postcode'],
]);

/*
 * The endpoint expects the WHOLE contact, because the form always sends
 * it. Pinned rather than left to be discovered: a partial submit blanks
 * what it omits. If a partial-update surface is ever added, this is the
 * test that will fail and say so.
 */
it('blanks omitted fields — the endpoint takes a full contact, not a patch', function () {
    [$tenant, $property] = fullContact();

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), [
        'email' => 'base@example.com',
        'role' => 'landlord',
    ]);

    $after = $property->fresh()->currentLandlordContact;

    expect($after->name)->toBeNull()
        ->and($after->organisation_name)->toBeNull()
        ->and($after->address_line1)->toBeNull()
        ->and($after->city)->toBeNull();
});
