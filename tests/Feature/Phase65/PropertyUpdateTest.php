<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the edit form for the property owner', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    $response = $this->actingAs($tenant)->get("/properties/{$property->id}/edit");

    $response->assertOk();
    $response->assertSee('Edit property');
    $response->assertSee($property->address_line1);
});

it('returns 403 when a tenant attempts to edit another tenant\'s property', function () {
    $tenant = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Property::factory()->create(['registered_by_user_id' => $other->id]);

    $response = $this->actingAs($tenant)->get("/properties/{$foreign->id}/edit");

    $response->assertForbidden();
});

it('updates an existing property without creating a new row', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create([
        'registered_by_user_id' => $tenant->id,
        'address_line1' => '12 Old Street',
        'city' => 'Manchester',
        'property_type' => 'terraced',
        'has_lease_agreement' => 'yes',
        'postcode' => 'M1 4ET',
    ]);

    $response = $this->actingAs($tenant)->patch("/properties/{$property->id}", [
        'address_line1' => '12 New Street',
        'address_line2' => 'Flat 2',
        'city' => 'Manchester',
        'property_type' => 'terraced',
        'has_lease_agreement' => 'yes',
        'postcode' => 'M1 4ET',
    ]);

    // 4 Oct 2026: an edit now finishes on /cases, where the form was
    // reached from. /properties left the nav and ending there left
    // the user on a page they had never seen.
    $response->assertRedirect('/cases');
    expect(Property::count())->toBe(1);

    $property->refresh();
    expect($property->address_line1)->toBe('12 New Street');
    expect($property->address_line2)->toBe('Flat 2');
});

it('returns 403 and does not modify when a tenant attempts to update another tenant\'s property', function () {
    $tenant = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Property::factory()->create([
        'registered_by_user_id' => $other->id,
        'address_line1' => '99 Untouchable Road',
        'city' => 'Leeds',
        'property_type' => 'terraced',
        'has_lease_agreement' => 'yes',
        'postcode' => 'LS1 1AA',
    ]);

    $response = $this->actingAs($tenant)->patch("/properties/{$foreign->id}", [
        'address_line1' => 'HACKED',
        'city' => 'Nowhere',
        'property_type' => 'terraced',
        'has_lease_agreement' => 'yes',
        'postcode' => 'M1 4ET',
    ]);

    $response->assertForbidden();

    $foreign->refresh();
    expect($foreign->address_line1)->toBe('99 Untouchable Road');
    expect($foreign->city)->toBe('Leeds');
});

it('rejects an update with a malformed postcode without modifying the row', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create([
        'registered_by_user_id' => $tenant->id,
        'postcode' => 'M1 4ET',
    ]);

    $response = $this->actingAs($tenant)->patch("/properties/{$property->id}", [
        'address_line1' => $property->address_line1,
        'city' => $property->city,
        'property_type' => 'terraced',
        'has_lease_agreement' => 'yes',
        'postcode' => 'not-a-postcode',
    ]);

    $response->assertSessionHasErrors('postcode');
    expect($property->fresh()->postcode)->toBe('M1 4ET');
});

it('redirects guests away from the edit form', function () {
    $property = Property::factory()->create();

    $response = $this->get("/properties/{$property->id}/edit");

    $response->assertRedirect('/login');
});

it('redirects guests away from PATCH /properties/{property}', function () {
    $property = Property::factory()->create();

    $response = $this->patch("/properties/{$property->id}", [
        'address_line1' => 'Hijack Lane',
        'city' => 'Anywhere',
        'property_type' => 'terraced',
        'has_lease_agreement' => 'yes',
        'postcode' => 'M1 4ET',
    ]);

    $response->assertRedirect('/login');
});
