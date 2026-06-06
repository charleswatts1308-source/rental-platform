<?php

use App\Models\LetterTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// These tests verify the lookup helpers against precisely-constructed
// fixtures. The global Pest beforeEach (tests/Pest.php) seeds the
// canonical rows for every Feature test; here we clear them so each
// case-under-test starts from an empty table.
beforeEach(function () {
    LetterTemplate::query()->delete();
});

it('forEscalation: prefers an active stage=N row over the NULL fallback', function () {
    $generic = LetterTemplate::create([
        'code' => 'generic_wakeup',
        'description' => 'Generic',
        'subject' => 'Generic',
        'body' => 'Generic body',
        'type' => 'escalation',
        'stage' => null,
        'active' => true,
    ]);
    $stage2 = LetterTemplate::create([
        'code' => 'stage_2_specific',
        'description' => 'Stage 2',
        'subject' => 'Stage 2',
        'body' => 'Stage 2 body',
        'type' => 'escalation',
        'stage' => 2,
        'active' => true,
    ]);

    expect(LetterTemplate::forEscalation(2)->id)->toBe($stage2->id);
    expect(LetterTemplate::forEscalation(3)->id)->toBe($generic->id); // no stage 3 row → fallback
});

it('forEscalation: skips inactive stage rows and falls back to NULL', function () {
    $generic = LetterTemplate::create([
        'code' => 'generic_wakeup',
        'description' => 'Generic',
        'subject' => 'Generic',
        'body' => 'Generic body',
        'type' => 'escalation',
        'stage' => null,
        'active' => true,
    ]);
    LetterTemplate::create([
        'code' => 'stage_1_disabled',
        'description' => 'Disabled stage 1',
        'subject' => 'Disabled',
        'body' => 'Disabled body',
        'type' => 'escalation',
        'stage' => 1,
        'active' => false,
    ]);

    expect(LetterTemplate::forEscalation(1)->id)->toBe($generic->id);
});

it('forEscalation: also falls back to NULL when the stage row exists but type is wrong', function () {
    $generic = LetterTemplate::create([
        'code' => 'generic_wakeup',
        'description' => 'Generic',
        'subject' => 'Generic',
        'body' => 'Generic body',
        'type' => 'escalation',
        'stage' => null,
        'active' => true,
    ]);
    LetterTemplate::create([
        'code' => 'stage_2_wrong_type',
        'description' => 'Wrong type',
        'subject' => 'Wrong',
        'body' => 'Wrong body',
        'type' => 'tenant_nudge',
        'stage' => 2,
        'active' => true,
    ]);

    expect(LetterTemplate::forEscalation(2)->id)->toBe($generic->id);
});

it('forEscalation: returns null when no escalation template is active at all', function () {
    LetterTemplate::create([
        'code' => 'inactive',
        'description' => 'Inactive',
        'subject' => 'X',
        'body' => 'X',
        'type' => 'escalation',
        'stage' => null,
        'active' => false,
    ]);

    expect(LetterTemplate::forEscalation(1))->toBeNull();
});

it('firstActiveOfType: returns the active row for the given type', function () {
    $nudge = LetterTemplate::create([
        'code' => 'nudge',
        'description' => 'Nudge',
        'subject' => 'X',
        'body' => 'X',
        'type' => 'tenant_nudge',
        'stage' => null,
        'active' => true,
    ]);
    LetterTemplate::create([
        'code' => 'unrelated',
        'description' => 'X',
        'subject' => 'X',
        'body' => 'X',
        'type' => 'escalation',
        'stage' => null,
        'active' => true,
    ]);

    expect(LetterTemplate::firstActiveOfType('tenant_nudge')->id)->toBe($nudge->id);
});

it('firstActiveOfType: returns null when no row of the requested type is active — optional-communication idiom', function () {
    LetterTemplate::create([
        'code' => 'inactive_closer',
        'description' => 'X',
        'subject' => 'X',
        'body' => 'X',
        'type' => 'exhaustion_landlord',
        'stage' => null,
        'active' => false,
    ]);

    expect(LetterTemplate::firstActiveOfType('exhaustion_landlord'))->toBeNull();
});
