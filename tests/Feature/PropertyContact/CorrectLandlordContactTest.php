<?php

use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Enums\LandlordContactRole;
use App\Models\CaseEvent;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function tenantWithContact(string $email = 'typo@example.com', ?string $name = 'Larry Landlord'): array
{
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);
    $property->setLandlordContact(
        ['email' => $email, 'name' => $name, 'role' => LandlordContactRole::Landlord],
        now(),
        $tenant->id,
    );

    return [$tenant, $property->fresh()];
}

function correctionPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'correct@example.com',
        'name' => 'Larry Landlord',
        'role' => 'landlord',
    ], $overrides);
}

/*
 * Snag #24 — the correction itself.
 */
it('inserts a new version and supersedes the old one', function () {
    [$tenant, $property] = tenantWithContact();
    $old = $property->currentLandlordContact;

    $this->actingAs($tenant)
        ->patch(route('properties.contact.update', $property), correctionPayload())
        ->assertRedirect(route('properties.contact.edit', $property));

    $property->refresh();

    expect($property->landlordContacts()->count())->toBe(2)
        ->and($property->currentLandlordContact->email)->toBe('correct@example.com')
        ->and($old->fresh()->superseded_at)->not->toBeNull()
        ->and($old->fresh()->is_current)->toBeNull()
        ->and($old->fresh()->email)->toBe('typo@example.com');
});

it('records who made the correction', function () {
    [$tenant, $property] = tenantWithContact();
    $old = $property->currentLandlordContact;

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    expect($property->fresh()->currentLandlordContact->created_by_user_id)->toBe($tenant->id)
        ->and($old->fresh()->superseded_by_user_id)->toBe($tenant->id);
});

it('creates version 1 when the property has no contact yet', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    expect($property->fresh()->landlordContacts()->count())->toBe(1)
        ->and($property->fresh()->currentLandlordContact->email)->toBe('correct@example.com');
});

it('does not manufacture a version when nothing changed', function () {
    [$tenant, $property] = tenantWithContact('same@example.com', 'Same Name');

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), [
        'email' => 'same@example.com',
        'name' => 'Same Name',
        'role' => 'landlord',
    ]);

    expect($property->fresh()->landlordContacts()->count())->toBe(1);
});

it('normalises the corrected email to lower case', function () {
    [$tenant, $property] = tenantWithContact();

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload([
        'email' => '  Correct@Example.COM  ',
    ]));

    expect($property->fresh()->currentLandlordContact->email)->toBe('correct@example.com');
});

/*
 * The evidential constraint, asserted at the surface a tenant actually
 * touches.
 */
it('leaves every frozen case_messages row untouched', function () {
    [$tenant, $property] = tenantWithContact();
    $case = RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);
    $case->messages()->create([
        'direction' => 'outbound',
        'sender_role' => 'system',
        'stage_at_send' => 1,
        'subject' => 'Repair notice 1',
        'body_raw' => 'Dear Larry Landlord, the boiler is broken.',
        'to_address_raw' => 'typo@example.com',
        'sent_at' => now(),
    ]);

    $before = DB::table('case_messages')->orderBy('id')->get()->toArray();

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    expect(DB::table('case_messages')->orderBy('id')->get()->toArray())->toEqual($before);
});

it('sends nothing when a correction is saved', function () {
    [$tenant, $property] = tenantWithContact();
    RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    Mail::assertNothingQueued();
});

it('does not advance current_stage on any case', function () {
    [$tenant, $property] = tenantWithContact();
    $case = RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => 2,
    ]);

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    expect($case->fresh()->current_stage)->toBe(2);
});

/*
 * The record of the correction.
 */
it('writes a landlord_contact_corrected event on every open case', function () {
    [$tenant, $property] = tenantWithContact();
    $openA = RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);
    $openB = RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::Open,
    ]);

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    foreach ([$openA, $openB] as $case) {
        $event = CaseEvent::where('case_id', $case->id)
            ->where('event_type', 'landlord_contact_corrected')
            ->sole();

        expect($event->meta)->toBe(['from' => 'typo@example.com', 'to' => 'correct@example.com'])
            ->and($event->actor_user_id)->toBe($tenant->id);
    }
});

it('does not write a correction event on a closed case', function () {
    [$tenant, $property] = tenantWithContact();
    $closed = RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::Resolved,
    ]);

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    expect(CaseEvent::where('case_id', $closed->id)
        ->where('event_type', 'landlord_contact_corrected')
        ->count())->toBe(0);
});

it('writes no correction event when creating version 1', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);
    $case = RepairCase::factory()->for($property)->create(['tenant_user_id' => $tenant->id]);

    // The factory gives every property a contact, so it has to be
    // removed to reach the genuine no-version-1-yet state. Nothing was
    // superseded, so nothing was corrected, so there is nothing to
    // record on the case.
    // Release the case's FK before deleting the row it restricts.
    $case->forceFill(['property_landlord_contact_id' => null])->saveQuietly();
    $property->landlordContacts()->delete();

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    expect($property->fresh()->currentLandlordContact->email)->toBe('correct@example.com')
        ->and(CaseEvent::where('case_id', $case->id)
            ->where('event_type', 'landlord_contact_corrected')
            ->count())->toBe(0);
});

/*
 * Landlord postal address — stored and displayed, never in a letter.
 */
it('stores the landlord postal address', function () {
    [$tenant, $property] = tenantWithContact();

    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload([
        'address_line1' => '1 Agency Row',
        'city' => 'Manchester',
        'postcode' => 'm1 1aa',
    ]));

    $contact = $property->fresh()->currentLandlordContact;

    expect($contact->address_line1)->toBe('1 Agency Row')
        ->and($contact->city)->toBe('Manchester')
        ->and($contact->postcode)->toBe('M1 1AA');
});

it('accepts a non-UK postcode, so an overseas agent cannot block service', function () {
    [$tenant, $property] = tenantWithContact();

    $this->actingAs($tenant)
        ->patch(route('properties.contact.update', $property), correctionPayload([
            'postcode' => '75008',
        ]))
        ->assertSessionHasNoErrors();

    expect($property->fresh()->currentLandlordContact->postcode)->toBe('75008');
});

it('keeps the landlord postal address out of the letter entirely', function () {
    [$tenant, $property] = tenantWithContact();
    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload([
        'address_line1' => 'Zzyzx Agency House',
        'city' => 'Manchester',
        'postcode' => 'M1 1AA',
    ]));

    $case = RepairCase::factory()->for($property)->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::Open,
    ]);

    app(SendCaseNotice::class)->execute($case->fresh());

    expect($case->messages()->sole()->body_raw)->not->toContain('Zzyzx Agency House');
});

/*
 * The page itself.
 */
it('shows the current contact and the full history', function () {
    [$tenant, $property] = tenantWithContact();
    $this->actingAs($tenant)->patch(route('properties.contact.update', $property), correctionPayload());

    $this->actingAs($tenant)
        ->get(route('properties.contact.edit', $property))
        ->assertOk()
        ->assertSee('correct@example.com')
        ->assertSee('typo@example.com')
        ->assertSee('Current');
});

it('labels a backfilled version as reconstructed rather than entered', function () {
    [$tenant, $property] = tenantWithContact();
    $property->currentLandlordContact->update(['source' => 'backfilled']);

    $this->actingAs($tenant)
        ->get(route('properties.contact.edit', $property))
        ->assertOk()
        ->assertSee('Reconstructed from earlier cases');
});

it('refuses another tenant access to the contact page', function () {
    [, $property] = tenantWithContact();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('properties.contact.edit', $property))
        ->assertForbidden();
});

it('refuses another tenant the ability to correct the contact', function () {
    [, $property] = tenantWithContact();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->patch(route('properties.contact.update', $property), correctionPayload())
        ->assertForbidden();

    expect($property->fresh()->currentLandlordContact->email)->toBe('typo@example.com');
});

it('requires a valid email', function () {
    [$tenant, $property] = tenantWithContact();

    $this->actingAs($tenant)
        ->patch(route('properties.contact.update', $property), correctionPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');

    expect($property->fresh()->landlordContacts()->count())->toBe(1);
});
