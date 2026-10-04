<?php

use App\Enums\LeaseAgreementAnswer;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Property type + "do you have a lease agreement?" — see
 * docs/cc-brief-property-details.md.
 *
 * Both are mandatory and both carry a `not_specified` case that exists
 * ONLY as a backfill marker for rows that pre-date the question. Most of
 * what is pinned here is that marker staying unreachable: if it can be
 * submitted, "the tenant chose Other" and "we never asked" merge, and
 * the statistics the fields exist for cannot be separated afterwards.
 */
function propertyDetailsPayload(array $overrides = []): array
{
    return array_merge([
        'address_line1' => '12 Example Street',
        'address_line2' => null,
        'city' => 'Manchester',
        'postcode' => 'M1 4ET',
        'property_type' => 'terraced',
        'has_lease_agreement' => 'yes',
    ], $overrides);
}

it('requires a property type', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/properties', propertyDetailsPayload(['property_type' => null]))
        ->assertSessionHasErrors('property_type');

    expect(Property::count())->toBe(0);
});

it('requires an answer about the lease agreement', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/properties', propertyDetailsPayload(['has_lease_agreement' => null]))
        ->assertSessionHasErrors('has_lease_agreement');

    expect(Property::count())->toBe(0);
});

// The backfill markers must be unreachable through the form. This is the
// assertion the whole "do not default existing rows to Other" ruling
// rests on.
it('refuses the not_specified backfill marker on either field', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/properties', propertyDetailsPayload(['property_type' => 'not_specified']))
        ->assertSessionHasErrors('property_type');

    $this->actingAs($user)
        ->post('/properties', propertyDetailsPayload(['has_lease_agreement' => 'not_specified']))
        ->assertSessionHasErrors('has_lease_agreement');

    expect(Property::count())->toBe(0);
});

it('refuses a value that is not in either enum', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/properties', propertyDetailsPayload(['property_type' => 'castle']))
        ->assertSessionHasErrors('property_type');

    $this->actingAs($user)
        ->post('/properties', propertyDetailsPayload(['has_lease_agreement' => 'maybe']))
        ->assertSessionHasErrors('has_lease_agreement');
});

it('stores both answers against the property', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post('/properties', propertyDetailsPayload([
        'property_type' => 'converted_flat',
        'has_lease_agreement' => 'unknown',
    ]));

    $property = Property::sole();

    expect($property->property_type)->toBe(PropertyType::ConvertedFlat)
        ->and($property->has_lease_agreement)->toBe(LeaseAgreementAnswer::DontKnow);
});

it('accepts all three lease answers', function (string $answer, LeaseAgreementAnswer $expected) {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post('/properties', propertyDetailsPayload([
        'has_lease_agreement' => $answer,
    ]));

    expect(Property::sole()->has_lease_agreement)->toBe($expected);
})->with([
    ['yes', LeaseAgreementAnswer::Yes],
    ['no', LeaseAgreementAnswer::No],
    ['unknown', LeaseAgreementAnswer::DontKnow],
]);

it('lets an existing property change both answers', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $property = Property::factory()->create([
        'registered_by_user_id' => $user->id,
        'property_type' => PropertyType::Terraced,
        'has_lease_agreement' => LeaseAgreementAnswer::Yes,
    ]);

    $this->actingAs($user)->patch("/properties/{$property->id}", propertyDetailsPayload([
        'address_line1' => $property->address_line1,
        'city' => $property->city,
        'postcode' => $property->postcode,
        'property_type' => 'park_home',
        'has_lease_agreement' => 'no',
    ]))->assertRedirect('/cases');

    expect($property->fresh()->property_type)->toBe(PropertyType::ParkHome)
        ->and($property->fresh()->has_lease_agreement)->toBe(LeaseAgreementAnswer::No);
});

// Belt and braces alongside the validation test: the marker must not be
// in the markup either, so nobody can pick it with the browser's own
// tooling and nobody adds it back by editing the dropdown.
it('never offers the backfill marker in the form', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/properties/create')
        ->assertOk()
        ->assertDontSee('not_specified')
        ->assertSee('Terraced house')
        // escaped, not raw: Blade renders the apostrophe as &#039;
        ->assertSee("I don't know");
});

// The ordering is the point of the list, not decoration: it follows the
// English PRS distribution published on the Background page so the two
// sets of figures can be read against each other.
it('offers the types commonest-first, matching the Background page', function () {
    expect(array_map(
        fn (PropertyType $type) => $type->value,
        array_slice(PropertyType::selectable(), 0, 6),
    ))->toBe([
        'terraced',
        'purpose_built_flat',
        'semi_detached',
        'converted_flat',
        'detached',
        'bungalow',
    ]);
});

it('keeps the backfill markers out of the selectable lists', function () {
    expect(PropertyType::selectable())->not->toContain(PropertyType::NotSpecified)
        ->and(LeaseAgreementAnswer::selectable())->not->toContain(LeaseAgreementAnswer::NotSpecified);
});
