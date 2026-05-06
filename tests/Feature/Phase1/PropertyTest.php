<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a valid property via factory', function () {
    $property = Property::factory()->create();

    expect($property->id)->toBeInt();
    expect($property->address_line1)->toBeString();
    expect($property->city)->toBeString();
    expect($property->postcode)->toBeString();
    expect($property->registered_by_user_id)->toBeInt();
});

it('allows a null address_line2', function () {
    $property = Property::factory()->create(['address_line2' => null]);

    expect($property->address_line2)->toBeNull();
});

it('belongs to the user who registered it', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $user->id]);

    expect($property->registeredBy)->toBeInstanceOf(User::class);
    expect($property->registeredBy->id)->toBe($user->id);
});
