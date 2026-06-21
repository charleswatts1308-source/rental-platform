<?php

use App\Enums\CaseStatus;
use App\Enums\ExhaustedStance;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// #21 (Option C) — the colliding stance dropdown is removed from the UI, but
// D14 is otherwise unchanged: an exhausted case stays revivable and closable,
// and the backend (enum / setStance action / policy / transitions) is untouched.

it('exhausted case page no longer renders the stance dropdown (#21)', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::EscalationExhausted,
    ]);

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertDontSee('How do you see this case?');
});

it('exhausted case page still offers reply, resolve and abandon (D14 preserved)', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::EscalationExhausted,
    ]);

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertSee('Send reply')
        ->assertSee('Mark resolved')
        ->assertSee('Abandon this case');
});

it('keeps the setStance backend intact (enum + policy unchanged)', function () {
    // The action is dormant in the UI but still wired — proves we reversed
    // nothing on the backend.
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::EscalationExhausted,
    ]);

    $this->actingAs($tenant)
        ->post("/cases/{$case->url_slug}/stance", ['stance' => ExhaustedStance::Unresolved->value])
        ->assertRedirect();

    expect($case->fresh()->exhausted_stance)->toBe(ExhaustedStance::Unresolved);
});
