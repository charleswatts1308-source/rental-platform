<?php

use App\Enums\ContactSource;
use App\Enums\LandlordContactRole;
use App\Models\Property;
use App\Models\PropertyLandlordContact;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a valid property landlord contact via factory', function () {
    $contact = PropertyLandlordContact::factory()->create();

    expect($contact->id)->toBeInt()
        ->and($contact->property_id)->toBeInt()
        ->and($contact->email)->toBeString()
        ->and($contact->role)->toBe(LandlordContactRole::Landlord)
        ->and($contact->source)->toBe(ContactSource::Entered)
        ->and($contact->is_current)->toBe(1)
        ->and($contact->superseded_at)->toBeNull();
});

it('casts effective_from and superseded_at to dates', function () {
    $contact = PropertyLandlordContact::factory()->superseded()->create();

    expect($contact->effective_from)->toBeInstanceOf(CarbonInterface::class)
        ->and($contact->superseded_at)->toBeInstanceOf(CarbonInterface::class);
});

/*
 * The Model A invariant, enforced by UNIQUE(property_id, is_current).
 * This is the constraint that stops a double-submitted edit forking a
 * property into two live contacts.
 */
it('refuses a second CURRENT contact on the same property', function () {
    $property = Property::factory()->create();
    PropertyLandlordContact::factory()->for($property)->create();

    PropertyLandlordContact::factory()->for($property)->create();
})->throws(QueryException::class);

it('permits many SUPERSEDED contacts on the same property', function () {
    $property = Property::factory()->create();

    PropertyLandlordContact::factory()->for($property)->superseded()->count(3)->create();
    PropertyLandlordContact::factory()->for($property)->create();

    expect($property->landlordContacts()->count())->toBe(4)
        ->and($property->currentLandlordContact()->count())->toBe(1);
});

it('permits the same email on two different properties (no global dedup)', function () {
    $a = Property::factory()->create();
    $b = Property::factory()->create();

    PropertyLandlordContact::factory()->for($a)->create(['email' => 'shared@agency.test']);
    PropertyLandlordContact::factory()->for($b)->create(['email' => 'shared@agency.test']);

    expect(PropertyLandlordContact::where('email', 'shared@agency.test')->count())->toBe(2);
});

it('resolves currentLandlordContact to the live version only', function () {
    $property = Property::factory()->create();
    PropertyLandlordContact::factory()->for($property)->superseded()->create(['email' => 'old@x.test']);
    $live = PropertyLandlordContact::factory()->for($property)->create(['email' => 'new@x.test']);

    expect($property->currentLandlordContact->id)->toBe($live->id)
        ->and($property->currentLandlordContact->email)->toBe('new@x.test');
});

it('supersedes the old version and installs the new one atomically', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();

    $first = $property->setLandlordContact(
        ['email' => 'first@x.test', 'name' => 'First', 'role' => LandlordContactRole::Landlord],
        now(),
        $user->id,
    );

    $second = $property->setLandlordContact(
        ['email' => 'second@x.test', 'name' => 'Second', 'role' => LandlordContactRole::Landlord],
        now()->addDay(),
        $user->id,
    );

    $first->refresh();

    expect($first->superseded_at)->not->toBeNull()
        ->and($first->is_current)->toBeNull()
        ->and($first->superseded_by_user_id)->toBe($user->id)
        ->and($second->is_current)->toBe(1)
        ->and($second->superseded_at)->toBeNull()
        ->and($property->refresh()->currentLandlordContact->id)->toBe($second->id)
        ->and($property->landlordContacts()->count())->toBe(2);
});

it('does not mutate the superseded row beyond retiring it', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();

    $first = $property->setLandlordContact(
        ['email' => 'typo@x.test', 'name' => 'Larry Landlord', 'role' => LandlordContactRole::Landlord],
        now(),
        $user->id,
    );
    $originalEmail = $first->email;
    $originalName = $first->name;
    $originalFrom = $first->effective_from;

    $property->setLandlordContact(
        ['email' => 'correct@x.test', 'name' => 'Larry Landlord', 'role' => LandlordContactRole::Landlord],
        now()->addDay(),
        $user->id,
    );

    $first->refresh();

    expect($first->email)->toBe($originalEmail)
        ->and($first->name)->toBe($originalName)
        ->and($first->effective_from->eq($originalFrom))->toBeTrue();
});

it('records who entered a version and stamps effective_from from the injected clock', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $at = now()->subDays(30)->startOfMinute();

    $contact = $property->setLandlordContact(
        ['email' => 'a@x.test', 'role' => LandlordContactRole::Landlord],
        $at,
        $user->id,
    );

    expect($contact->created_by_user_id)->toBe($user->id)
        ->and($contact->effective_from->eq($at))->toBeTrue();
});

it('marks backfilled rows so history can distinguish them from real edits', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();

    $contact = $property->setLandlordContact(
        ['email' => 'a@x.test', 'role' => LandlordContactRole::Landlord],
        now(),
        $user->id,
        ContactSource::Backfilled,
    );

    expect($contact->source)->toBe(ContactSource::Backfilled);
});

it('round-trips the landlord postal address and exposes it as display lines', function () {
    $contact = PropertyLandlordContact::factory()->create([
        'address_line1' => '1 Agency Row',
        'address_line2' => null,
        'city' => 'Manchester',
        'postcode' => 'M1 1AA',
    ]);

    expect($contact->fresh()->postalAddressLines())
        ->toBe(['1 Agency Row', 'Manchester', 'M1 1AA'])
        ->and($contact->hasPostalAddress())->toBeTrue();
});

it('reports no postal address when the fields are empty', function () {
    $contact = PropertyLandlordContact::factory()->create();

    expect($contact->postalAddressLines())->toBe([])
        ->and($contact->hasPostalAddress())->toBeFalse();
});

it('orders the contact history newest first', function () {
    $property = Property::factory()->create();
    PropertyLandlordContact::factory()->for($property)->superseded()->create([
        'email' => 'oldest@x.test', 'effective_from' => now()->subDays(10),
    ]);
    PropertyLandlordContact::factory()->for($property)->superseded()->create([
        'email' => 'middle@x.test', 'effective_from' => now()->subDays(5),
    ]);
    PropertyLandlordContact::factory()->for($property)->create([
        'email' => 'newest@x.test', 'effective_from' => now(),
    ]);

    expect($property->landlordContacts->pluck('email')->all())
        ->toBe(['newest@x.test', 'middle@x.test', 'oldest@x.test']);
});
