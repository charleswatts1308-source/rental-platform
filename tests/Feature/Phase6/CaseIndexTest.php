<?php

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

it('shows a friendly empty state when the tenant has no cases', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/cases');

    $response->assertOk();
    $response->assertSee("haven't raised any repair cases", false);
});
