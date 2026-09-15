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

    // #66: registering a property now carries the user to that property's
    // landlord step, not to raise-a-case, and does so for every property
    // rather than only the first. Both the first and subsequent cases are
    // asserted in tests/Feature/DashboardOnboardingTest.php.
    $response->assertRedirect('/properties/'.Property::firstOrFail()->id.'/landlord');
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

    // Onboarding redirect — now the landlord step (#66); see the storage
    // test above.
    $response->assertRedirect('/properties/'.Property::firstOrFail()->id.'/landlord');
    expect(Property::firstOrFail()->postcode)->toBe('EC1A 1BB');
});

it('redirects guests away from POST /properties', function () {
    $response = $this->post('/properties', validPropertyPayload());

    $response->assertRedirect('/login');
});

/**
 * Raised by Charlie 15 Sep, walking the form: the Register button needed
 * TWO clicks. The postcode lookup fires on blur, and the hint it wrote
 * appeared below the field, pushing the button down a line at the exact
 * moment the click was landing. Space is reserved for the hint now, so
 * nothing moves.
 *
 * Asserted at the markup level because the cause is layout, not logic —
 * there is no request to assert on. This pins what makes
 * the shift impossible: one hint line, never `d-none`, always holding
 * its reserved line. One and not two: the postcode used to carry its
 * own, which showed as empty space mid-form once the town moved beneath
 * it.
 */
it('reserves space for the postcode hints so the button cannot move under the cursor', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get('/properties/create')->getContent();

    expect($html)->toContain('id="field-hint" class="form-text" style="min-height:1.5rem"');
    expect($html)->not->toContain('id="field-hint" class="form-text d-none"');
    expect(substr_count($html, 'min-height:1.5rem'))->toBe(1);
});

it('puts the postcode before the city and says the city may be filled in', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get('/properties/create')->getContent();

    expect(strpos($html, 'for="postcode"'))->toBeLessThan(strpos($html, 'for="city"'));
    expect($html)->toContain('Type the postcode above, then click in this box and we will fill it in for you.');
});

/**
 * The town sits BENEATH the postcode, not beside it, and the help text
 * tells the user what to DO rather than describing what might happen
 * (both asked for 15 Sep). The column break is what stacks them while
 * letting each keep its own width.
 */
it('stacks the town beneath the postcode rather than alongside it', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get('/properties/create')->getContent();

    $postcodeAt = strpos($html, 'for="postcode"');
    $breakAt = strpos($html, '<div class="w-100"></div>');
    $cityAt = strpos($html, 'for="city"');

    expect($postcodeAt)->toBeLessThan($breakAt);
    expect($breakAt)->toBeLessThan($cityAt);
});
