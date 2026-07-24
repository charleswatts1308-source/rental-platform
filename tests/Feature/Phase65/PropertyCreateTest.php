<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function validPropertyPayload(array $overrides = []): array
{
    return array_merge([
        'address_line1' => '12 Mulberry Lane',
        'address_line2' => 'Flat 4',
        'city' => 'Manchester',
        'postcode' => 'M1 4ET',
    ], $overrides);
}

it('redirects guests away from /properties/create', function () {
    $response = $this->get('/properties/create');

    $response->assertRedirect('/login');
});

it('renders the create form for authenticated tenants', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/properties/create');

    $response->assertOk();
    $response->assertSee('Register a property');
    $response->assertSee('Postcode');
});

it('stores a property with registered_by_user_id set to the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', validPropertyPayload());

    // First property for this user, so the onboarding redirect carries them
    // on to raise a case rather than back to the list. Subsequent properties
    // still land on /properties — both branches covered in
    // tests/Feature/DashboardOnboardingTest.php.
    $response->assertRedirect('/cases/create');
    expect(Property::count())->toBe(1);

    $property = Property::firstOrFail();
    expect($property->registered_by_user_id)->toBe($user->id);
    expect($property->address_line1)->toBe('12 Mulberry Lane');
    expect($property->city)->toBe('Manchester');
});

it('normalises postcode to upper-case with a single space before the inward part', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/properties', validPropertyPayload(['postcode' => 'm14et']));

    expect(Property::firstOrFail()->postcode)->toBe('M1 4ET');
});

it('rejects a payload missing required fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', []);

    $response->assertSessionHasErrors(['address_line1', 'city', 'postcode']);
    expect(Property::count())->toBe(0);
});

it('rejects a malformed postcode', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', validPropertyPayload(['postcode' => 'not-a-postcode']));

    $response->assertSessionHasErrors('postcode');
    expect(Property::count())->toBe(0);
});

it('accepts a valid UK postcode without a space', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', validPropertyPayload(['postcode' => 'EC1A1BB']));

    // First property → onboarding redirect (see the storage test above).
    $response->assertRedirect('/cases/create');
    expect(Property::firstOrFail()->postcode)->toBe('EC1A 1BB');
});

it('redirects guests away from POST /properties', function () {
    $response = $this->post('/properties', validPropertyPayload());

    $response->assertRedirect('/login');
});
