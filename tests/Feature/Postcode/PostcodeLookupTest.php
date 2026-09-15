<?php

use App\Models\User;
use App\Services\PostcodeLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The lookup is OFF in phpunit.xml so that no other test can ever reach
// the live service. These are the tests that exercise it, so they switch
// it on deliberately — with Http faked in every single case.
beforeEach(function () {
    config(['services.postcodes.enabled' => true]);
});

/*
 * #51 — postcode existence and town reconciliation.
 *
 * Every test here stubs the HTTP layer. The suite must never depend on a
 * live third-party host, and the fail-open behaviour is impossible to
 * prove against a service that happens to be up.
 */

function postcodePayload(string $postcode, string $district): array
{
    return [
        'status' => 200,
        'result' => [
            'postcode' => $postcode,
            'admin_district' => $district,
        ],
    ];
}

function propertyPayload(array $overrides = []): array
{
    return array_merge([
        'address_line1' => '66 Pond Road',
        'city' => 'Reading',
        'postcode' => 'RG1 5SE',
    ], $overrides);
}

it('records a property when the postcode exists', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response(postcodePayload('RG1 5SE', 'Reading')),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', propertyPayload());

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('properties', ['postcode' => 'RG1 5SE']);
});

it('refuses a well-formed postcode the service says does not exist', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response(['status' => 404, 'error' => 'Postcode not found'], 404),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', propertyPayload([
        'postcode' => 'ZZ1 1ZZ',
    ]));

    $response->assertSessionHasErrors('postcode');
    expect(\App\Models\Property::count())->toBe(0);
});

/*
 * The three fail-open tests. These are the point of the feature, not an
 * edge case: a tenant with a broken boiler must never be blocked from
 * registering a property because an address-tidying service is down.
 */

it('FAILS OPEN when the lookup times out', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
    });

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', propertyPayload([
        'postcode' => 'ZZ1 1ZZ',
    ]));

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('properties', ['postcode' => 'ZZ1 1ZZ']);
});

it('FAILS OPEN when the service returns a server error', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response('upstream exploded', 503),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', propertyPayload([
        'postcode' => 'ZZ1 1ZZ',
    ]));

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('properties', ['postcode' => 'ZZ1 1ZZ']);
});

it('FAILS OPEN when the lookup is switched off', function () {
    config(['services.postcodes.enabled' => false]);
    Http::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', propertyPayload([
        'postcode' => 'ZZ1 1ZZ',
    ]));

    $response->assertSessionHasNoErrors();
    Http::assertNothingSent();
});

it('still enforces the shape regex, lookup or no lookup', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response(postcodePayload('RG1 5SE', 'Reading')),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/properties', propertyPayload([
        'postcode' => 'not a postcode',
    ]));

    $response->assertSessionHasErrors('postcode');
});

it('caches a definite answer and does not ask twice', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response(postcodePayload('RG1 5SE', 'Reading')),
    ]);

    $lookup = app(PostcodeLookup::class);

    $lookup->lookup('RG1 5SE');
    $lookup->lookup('rg15se');   // same postcode, different spacing and case

    Http::assertSentCount(1);
});

it('does NOT cache an unknown, so an outage does not outlive itself', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response('', 500),
    ]);

    $lookup = app(PostcodeLookup::class);

    $lookup->lookup('RG1 5SE');
    $lookup->lookup('RG1 5SE');

    Http::assertSentCount(2);
    expect(Cache::has('postcode-lookup:RG15SE'))->toBeFalse();
});

it('returns the district from the lookup endpoint', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response(postcodePayload('RG1 5SE', 'Reading')),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/postcode-lookup?postcode=RG1+5SE');

    $response->assertOk();
    $response->assertJson([
        'status' => 'exists',
        'postcode' => 'RG1 5SE',
        'district' => 'Reading',
    ]);
});

it('answers unknown rather than erroring when the endpoint is given nothing', function () {
    Http::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/postcode-lookup?postcode=');

    $response->assertOk();
    $response->assertJson(['status' => 'unknown']);
    Http::assertNothingSent();
});

it('keeps the lookup endpoint behind auth', function () {
    $response = $this->getJson('/postcode-lookup?postcode=RG1+5SE');

    $response->assertUnauthorized();
});
