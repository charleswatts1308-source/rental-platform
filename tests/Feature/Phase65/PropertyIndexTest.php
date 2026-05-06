<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests away from /properties', function () {
    $response = $this->get('/properties');

    $response->assertRedirect('/login');
});

it('shows the index to authenticated tenants', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/properties');

    $response->assertOk();
    $response->assertSee('My Properties');
});

it('lists only the authenticated tenant\'s properties', function () {
    $tenant = User::factory()->create();
    $other = User::factory()->create();

    $mine = Property::factory()->create([
        'registered_by_user_id' => $tenant->id,
        'address_line1' => '12 My Street',
    ]);
    Property::factory()->create([
        'registered_by_user_id' => $other->id,
        'address_line1' => '99 Other Lane',
    ]);

    $response = $this->actingAs($tenant)->get('/properties');

    $response->assertOk();
    $response->assertSee('12 My Street');
    $response->assertDontSee('99 Other Lane');
});

it('shows an empty state when the tenant has no properties', function () {
    $tenant = User::factory()->create();

    $response = $this->actingAs($tenant)->get('/properties');

    $response->assertOk();
    $response->assertSee("haven't registered any properties", false);
});
