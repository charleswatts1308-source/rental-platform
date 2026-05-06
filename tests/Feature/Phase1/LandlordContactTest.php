<?php

use App\Enums\LandlordContactRole;
use App\Models\LandlordContact;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a valid landlord contact via factory', function () {
    $contact = LandlordContact::factory()->create();

    expect($contact->id)->toBeInt();
    expect($contact->email)->toBeString();
    expect($contact->role)->toBe(LandlordContactRole::Landlord);
    expect($contact->invited_by_user_id)->toBeInt();
});

it('casts role to LandlordContactRole enum', function () {
    $contact = LandlordContact::factory()->create(['role' => 'agent']);

    expect($contact->role)->toBe(LandlordContactRole::Agent);
});

it('casts verified_at to a datetime', function () {
    $contact = LandlordContact::factory()->create(['verified_at' => now()]);

    expect($contact->verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('belongs to the user who invited them', function () {
    $user = User::factory()->create();
    $contact = LandlordContact::factory()->create(['invited_by_user_id' => $user->id]);

    expect($contact->invitedBy)->toBeInstanceOf(User::class);
    expect($contact->invitedBy->id)->toBe($user->id);
});

it('enforces unique email at the database level', function () {
    LandlordContact::factory()->create(['email' => 'duplicate@example.com']);

    LandlordContact::factory()->create(['email' => 'duplicate@example.com']);
})->throws(QueryException::class);

it('produces an agent contact with organisation_name via the agent state', function () {
    $contact = LandlordContact::factory()->agent()->create();

    expect($contact->role)->toBe(LandlordContactRole::Agent);
    expect($contact->organisation_name)->toBeString();
});
