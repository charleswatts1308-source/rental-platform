<?php

use App\Enums\ContactSource;
use App\Models\Property;
use App\Models\PropertyLandlordContact;
use App\Models\RepairCase;
use App\Models\User;
use App\Support\BackfillPropertyLandlordContacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Put the database back into its pre-migration shape.
 *
 * RepairCaseFactory now gives every property a current landlord contact,
 * which is right for every other test and wrong for these: the backfill's
 * entire premise is legacy data where no property contact exists yet.
 * Without this the backfill would collide with the fixture on
 * UNIQUE(property_id, is_current) — correctly, which is the point.
 */
function revertToPreMigrationState(): void
{
    DB::table('cases')->update(['property_landlord_contact_id' => null]);
    DB::table('property_landlord_contacts')->delete();
}

/**
 * One row in the shape the migration's join produces.
 *
 * The backfill takes legacy rows as an ARGUMENT rather than reading
 * landlord_contacts, so these tests keep working after that table is
 * dropped in the final commit.
 */
function legacyRow(int $caseId, int $propertyId, string $openedAt, string $email, int $userId, ?string $name = null, string $role = 'landlord', ?string $org = null): object
{
    return (object) [
        'case_id' => $caseId,
        'property_id' => $propertyId,
        'opened_at' => $openedAt,
        'email' => $email,
        'name' => $name,
        'role' => $role,
        'organisation_name' => $org,
        'invited_by_user_id' => $userId,
    ];
}

it('creates one version and repoints the case when a property has a single case', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $case = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    $result = (new BackfillPropertyLandlordContacts)([
        legacyRow($case->id, $property->id, '2026-01-01 09:00:00', 'larry@x.test', $user->id, 'Larry Landlord'),
    ]);

    expect($result)->toBe([
        'versions_created' => 1,
        'cases_repointed' => 1,
        'properties_touched' => 1,
    ]);

    $version = PropertyLandlordContact::sole();

    expect($version->property_id)->toBe($property->id)
        ->and($version->email)->toBe('larry@x.test')
        ->and($version->name)->toBe('Larry Landlord')
        ->and($version->is_current)->toBe(1)
        ->and($version->superseded_at)->toBeNull()
        ->and($version->effective_from->toDateTimeString())->toBe('2026-01-01 09:00:00')
        ->and($case->fresh()->property_landlord_contact_id)->toBe($version->id);
});

it('stamps every backfilled row as inferred, not entered', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $case = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($case->id, $property->id, '2026-01-01 09:00:00', 'a@x.test', $user->id),
    ]);

    expect(PropertyLandlordContact::sole()->source)->toBe(ContactSource::Backfilled);
});

it('collapses repeated cases on the same email into ONE version', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $first = RepairCase::factory()->for($property)->create();
    $second = RepairCase::factory()->for($property)->create();
    $third = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    $result = (new BackfillPropertyLandlordContacts)([
        legacyRow($first->id, $property->id, '2026-01-01 09:00:00', 'same@x.test', $user->id),
        legacyRow($second->id, $property->id, '2026-02-01 09:00:00', 'same@x.test', $user->id),
        legacyRow($third->id, $property->id, '2026-03-01 09:00:00', 'same@x.test', $user->id),
    ]);

    expect($result['versions_created'])->toBe(1)
        ->and($result['cases_repointed'])->toBe(3);

    $version = PropertyLandlordContact::sole();

    expect($first->fresh()->property_landlord_contact_id)->toBe($version->id)
        ->and($second->fresh()->property_landlord_contact_id)->toBe($version->id)
        ->and($third->fresh()->property_landlord_contact_id)->toBe($version->id);
});

it('chains a version change when the email differs, closing the old one at the new case date', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $first = RepairCase::factory()->for($property)->create();
    $second = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($first->id, $property->id, '2026-01-01 09:00:00', 'old@x.test', $user->id),
        legacyRow($second->id, $property->id, '2026-06-01 09:00:00', 'new@x.test', $user->id),
    ]);

    $old = PropertyLandlordContact::where('email', 'old@x.test')->sole();
    $new = PropertyLandlordContact::where('email', 'new@x.test')->sole();

    expect($old->effective_from->toDateTimeString())->toBe('2026-01-01 09:00:00')
        ->and($old->superseded_at->toDateTimeString())->toBe('2026-06-01 09:00:00')
        ->and($old->is_current)->toBeNull()
        ->and($new->effective_from->toDateTimeString())->toBe('2026-06-01 09:00:00')
        ->and($new->superseded_at)->toBeNull()
        ->and($new->is_current)->toBe(1);
});

it('points each case at the version in force when THAT case was raised', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $first = RepairCase::factory()->for($property)->create();
    $second = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($first->id, $property->id, '2026-01-01 09:00:00', 'old@x.test', $user->id),
        legacyRow($second->id, $property->id, '2026-06-01 09:00:00', 'new@x.test', $user->id),
    ]);

    $old = PropertyLandlordContact::where('email', 'old@x.test')->sole();
    $new = PropertyLandlordContact::where('email', 'new@x.test')->sole();

    expect($first->fresh()->property_landlord_contact_id)->toBe($old->id)
        ->and($second->fresh()->property_landlord_contact_id)->toBe($new->id);
});

it('leaves exactly one current version per property', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $cases = RepairCase::factory()->for($property)->count(4)->create();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($cases[0]->id, $property->id, '2026-01-01 09:00:00', 'a@x.test', $user->id),
        legacyRow($cases[1]->id, $property->id, '2026-02-01 09:00:00', 'b@x.test', $user->id),
        legacyRow($cases[2]->id, $property->id, '2026-03-01 09:00:00', 'c@x.test', $user->id),
        legacyRow($cases[3]->id, $property->id, '2026-04-01 09:00:00', 'd@x.test', $user->id),
    ]);

    expect(PropertyLandlordContact::count())->toBe(4)
        ->and(PropertyLandlordContact::whereNull('superseded_at')->count())->toBe(1)
        ->and($property->fresh()->currentLandlordContact->email)->toBe('d@x.test');
});

it('opens a fresh version when an old email returns later (timeline, not a set)', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $cases = RepairCase::factory()->for($property)->count(3)->create();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($cases[0]->id, $property->id, '2026-01-01 09:00:00', 'a@x.test', $user->id),
        legacyRow($cases[1]->id, $property->id, '2026-02-01 09:00:00', 'b@x.test', $user->id),
        legacyRow($cases[2]->id, $property->id, '2026-03-01 09:00:00', 'a@x.test', $user->id),
    ]);

    expect(PropertyLandlordContact::count())->toBe(3)
        ->and(PropertyLandlordContact::where('email', 'a@x.test')->count())->toBe(2)
        ->and($property->fresh()->currentLandlordContact->email)->toBe('a@x.test');
});

it('keeps properties independent of one another', function () {
    $user = User::factory()->create();
    $a = Property::factory()->create();
    $b = Property::factory()->create();
    $caseA = RepairCase::factory()->for($a)->create();
    $caseB = RepairCase::factory()->for($b)->create();

    revertToPreMigrationState();

    $result = (new BackfillPropertyLandlordContacts)([
        legacyRow($caseA->id, $a->id, '2026-01-01 09:00:00', 'shared@agency.test', $user->id),
        legacyRow($caseB->id, $b->id, '2026-01-02 09:00:00', 'shared@agency.test', $user->id),
    ]);

    // The same address on two properties is TWO rows now. Under the old
    // global unique index it was one, which is snag #49(a).
    expect($result['properties_touched'])->toBe(2)
        ->and(PropertyLandlordContact::where('email', 'shared@agency.test')->count())->toBe(2)
        ->and($a->fresh()->currentLandlordContact->id)
        ->not->toBe($b->fresh()->currentLandlordContact->id);
});

it('normalises case and whitespace the way the old resolver stored it', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $first = RepairCase::factory()->for($property)->create();
    $second = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    $result = (new BackfillPropertyLandlordContacts)([
        legacyRow($first->id, $property->id, '2026-01-01 09:00:00', 'Larry@X.test', $user->id),
        legacyRow($second->id, $property->id, '2026-02-01 09:00:00', '  larry@x.test  ', $user->id),
    ]);

    // Same address either side of the normalisation: one version, not a
    // fabricated change of landlord.
    expect($result['versions_created'])->toBe(1)
        ->and(PropertyLandlordContact::sole()->email)->toBe('larry@x.test');
});

it('sorts by opened_at regardless of the order rows arrive in', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $early = RepairCase::factory()->for($property)->create();
    $late = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($late->id, $property->id, '2026-06-01 09:00:00', 'new@x.test', $user->id),
        legacyRow($early->id, $property->id, '2026-01-01 09:00:00', 'old@x.test', $user->id),
    ]);

    expect($property->fresh()->currentLandlordContact->email)->toBe('new@x.test')
        ->and($early->fresh()->property_landlord_contact_id)
        ->toBe(PropertyLandlordContact::where('email', 'old@x.test')->sole()->id);
});

it('carries name, role and organisation across to the new row', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $case = RepairCase::factory()->for($property)->create();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($case->id, $property->id, '2026-01-01 09:00:00', 'agent@x.test', $user->id, 'Jane Agent', 'agent', 'Agency Ltd'),
    ]);

    $version = PropertyLandlordContact::sole();

    expect($version->name)->toBe('Jane Agent')
        ->and($version->role->value)->toBe('agent')
        ->and($version->organisation_name)->toBe('Agency Ltd')
        ->and($version->created_by_user_id)->toBe($user->id);
});

it('does nothing at all when there are no legacy rows', function () {
    revertToPreMigrationState();

    $result = (new BackfillPropertyLandlordContacts)([]);

    expect($result)->toBe([
        'versions_created' => 0,
        'cases_repointed' => 0,
        'properties_touched' => 0,
    ])->and(PropertyLandlordContact::count())->toBe(0);
});

it('never touches case_messages', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $case = RepairCase::factory()->for($property)->create();

    $case->messages()->create([
        'direction' => 'outbound',
        'sender_role' => 'system',
        'stage_at_send' => 1,
        'subject' => 'Repair notice 1',
        'body_raw' => 'Dear Larry Landlord, the boiler is broken.',
        'to_address_raw' => 'typo@x.test',
        'sent_at' => now(),
    ]);

    $before = DB::table('case_messages')->orderBy('id')->get()->toArray();

    revertToPreMigrationState();

    (new BackfillPropertyLandlordContacts)([
        legacyRow($case->id, $property->id, '2026-01-01 09:00:00', 'corrected@x.test', $user->id, 'Larry Landlord'),
    ]);

    expect(DB::table('case_messages')->orderBy('id')->get()->toArray())->toEqual($before);
});
