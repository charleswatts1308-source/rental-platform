<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 403 for an authenticated non-admin on an /admin/* route', function () {
    $user = User::factory()->create(); // is_admin defaults to false

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

it('allows an admin onto an /admin/* route', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

it('does not let a crafted profile-update POST escalate is_admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => 1, // attempted privilege escalation
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->is_admin)->toBeFalse();
});

it('keeps is_admin out of the User mass-assignable set', function () {
    expect((new User)->getFillable())->not->toContain('is_admin');
});
