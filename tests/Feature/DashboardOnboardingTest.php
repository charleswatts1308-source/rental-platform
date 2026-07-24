<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The dashboard is the post-verification landing page and the site's
 * signposting hub. It had NO test coverage before this file, so the green
 * suite said nothing about whether it rendered at all.
 */
it('renders for a brand-new user and points them at registering a property', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Start here')
        ->assertSee('Register your property')
        ->assertSee(route('properties.create'));
});

it('points a user with a property at raising their first case', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Property::factory()->create(['registered_by_user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('ready to raise a repair case')
        ->assertSee(route('cases.create'))
        ->assertDontSee('Start here');
});

it('carries a user straight to raise-a-case after their FIRST property', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('properties.store'), [
            'address_line1' => '1 Test Street',
            'city' => 'Leeds',
            'postcode' => 'LS1 1AA',
        ])
        ->assertRedirect(route('cases.create'))
        ->assertSessionHas('success');
});

it('keeps a user on the properties list for a SUBSEQUENT property', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Property::factory()->create(['registered_by_user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('properties.store'), [
            'address_line1' => '2 Test Street',
            'city' => 'Leeds',
            'postcode' => 'LS2 2BB',
        ])
        ->assertRedirect(route('properties.index'));
});

it('confirms the single property on a line instead of asking you to select it', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $property = Property::factory()->create([
        'registered_by_user_id' => $user->id,
        'address_line1' => '12 Mulberry Lane',
        'postcode' => 'M1 4ET',
    ]);

    $response = $this->actingAs($user)->get(route('cases.create'));

    $response->assertOk()
        ->assertSee('12 Mulberry Lane')
        ->assertSee('M1 4ET')
        ->assertSee('name="property_id" value="'.$property->id.'"', false)
        ->assertDontSee('— select a property —');
});

it('still offers a select when the user has more than one property', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Property::factory()->count(2)->create(['registered_by_user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('cases.create'))
        ->assertOk()
        ->assertSee('— select a property —');
});

it('rejects a property_id belonging to another user', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Property::factory()->create(['registered_by_user_id' => $user->id]);

    $someoneElse = User::factory()->create();
    $theirProperty = Property::factory()->create(['registered_by_user_id' => $someoneElse->id]);

    // The hidden input is not a trust boundary: ownership is enforced by the
    // exists rule, scoped to the authenticated user.
    $this->actingAs($user)
        ->post(route('cases.store'), ['property_id' => $theirProperty->id])
        ->assertSessionHasErrors('property_id');
});

it('requires auth and verification', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $unverified = User::factory()->create(['email_verified_at' => null]);
    $this->actingAs($unverified)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});
