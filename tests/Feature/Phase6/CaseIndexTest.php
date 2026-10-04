<?php

use App\Models\Property;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests away from the case index', function () {
    $response = $this->get('/cases');

    $response->assertRedirect('/login');
});

it('shows the case index to authenticated tenants', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/cases');

    $response->assertOk();
    $response->assertSee('My Repair Cases');
});

it('lists only the authenticated tenant\'s own cases', function () {
    $tenant = User::factory()->create();
    $otherTenant = User::factory()->create();

    $myCase = RepairCase::factory()->create(['tenant_user_id' => $tenant->id]);
    $otherCase = RepairCase::factory()->create(['tenant_user_id' => $otherTenant->id]);

    $response = $this->actingAs($tenant)->get('/cases');

    $response->assertOk();
    $response->assertSee($myCase->url_slug);
    $response->assertDontSee($otherCase->url_slug);
});

// The two empty states this page can be in. It absorbed the dashboard on
// 4 Oct 2026, so a tenant with nothing at all lands HERE straight after
// verifying and must be told what to do first: property, then case.
it('points a tenant with nothing at registering a property', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/cases');

    $response->assertOk();
    $response->assertSee('Start here');
    $response->assertSee('Register your property');
});

it('shows a property with no cases under its own heading', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/cases');

    $response->assertOk();
    $response->assertSee($property->address_line1);
    $response->assertSee('No repair cases at this address yet');
    // The landlord control belongs to the property, so it must be reachable
    // from the heading even before a single case exists.
    $response->assertSee(route('properties.contact.edit', $property), false);
    // 4 Oct 2026: the onboarding card lost its "Raise a repair case" button
    // because it duplicated this one. That makes the property heading the
    // ONLY way a first-time tenant can raise a case, so pin it here --
    // and pin the preselection, which is why this button is the better one.
    $response->assertSee(route('cases.create', ['property' => $property->id]), false);
});
