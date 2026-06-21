<?php

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Models\LandlordContact;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates a valid repair case via factory', function () {
    $case = RepairCase::factory()->create();

    expect($case->id)->toBeInt();
    expect($case->url_slug)->toBeString();
    // D16 #4 — references are now 6 chars from the read-aloud-safe alphabet
    // (A–Z + 2–9, no I/O/0/1). Stronger than the old length-only check.
    expect($case->url_slug)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
    expect($case->status)->toBe(CaseStatus::Open);
    expect($case->severity)->toBe(CaseSeverity::Routine);
    expect($case->current_stage)->toBe(1);
});

it('uses the cases table', function () {
    expect((new RepairCase())->getTable())->toBe('cases');
});

it('casts status to CaseStatus enum', function () {
    $case = RepairCase::factory()->create(['status' => 'awaiting_landlord']);

    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('casts severity to CaseSeverity enum', function () {
    $case = RepairCase::factory()->create(['severity' => 'emergency']);

    expect($case->severity)->toBe(CaseSeverity::Emergency);
});

it('casts current_stage to integer', function () {
    $case = RepairCase::factory()->create(['current_stage' => 3]);

    expect($case->current_stage)->toBe(3)->toBeInt();
});

it('casts datetime columns to Carbon instances', function () {
    $case = RepairCase::factory()->create([
        'silence_clock_started_at' => now(),
        'hold_until' => now()->addDays(7),
        'closed_at' => now(),
    ]);

    expect($case->silence_clock_started_at)->toBeInstanceOf(Carbon::class);
    expect($case->hold_until)->toBeInstanceOf(Carbon::class);
    expect($case->opened_at)->toBeInstanceOf(Carbon::class);
    expect($case->closed_at)->toBeInstanceOf(Carbon::class);
});

it('belongs to a tenant user', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create(['tenant_user_id' => $tenant->id]);

    expect($case->tenant)->toBeInstanceOf(User::class);
    expect($case->tenant->id)->toBe($tenant->id);
});

it('belongs to a property', function () {
    $property = Property::factory()->create();
    $case = RepairCase::factory()->create(['property_id' => $property->id]);

    expect($case->property)->toBeInstanceOf(Property::class);
    expect($case->property->id)->toBe($property->id);
});

it('belongs to a landlord contact', function () {
    $contact = LandlordContact::factory()->create();
    $case = RepairCase::factory()->create(['landlord_contact_id' => $contact->id]);

    expect($case->landlordContact)->toBeInstanceOf(LandlordContact::class);
    expect($case->landlordContact->id)->toBe($contact->id);
});

it('exposes a cases hasMany relationship on LandlordContact', function () {
    $contact = LandlordContact::factory()->create();
    RepairCase::factory()->count(3)->create(['landlord_contact_id' => $contact->id]);

    expect($contact->cases)->toHaveCount(3);
    expect($contact->cases->first())->toBeInstanceOf(RepairCase::class);
});

it('enforces unique url_slug at the database level', function () {
    RepairCase::factory()->create(['url_slug' => 'duplicate123']);
    RepairCase::factory()->create(['url_slug' => 'duplicate123']);
})->throws(QueryException::class);
