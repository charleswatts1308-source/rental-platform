<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

/**
 * Phase 3 — sendNext and reEngage demolished. Hold + resolve +
 * abandon still here; their valid-state lists are updated to the
 * new transitions map (TAR is gone). Reply tests live in the
 * SilencePhase3 suite.
 */
function tenantOwning(CaseStatus $status, array $overrides = []): array
{
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->withLandlord([])->create(array_merge([
        'tenant_user_id' => $tenant->id,
        'status' => $status,
    ], $overrides));

    return [$tenant, $case];
}

// ---------- hold ----------

it('hold pauses the case from awaiting_tenant_review and awaiting_landlord', function () {
    foreach ([CaseStatus::AwaitingTenantReview, CaseStatus::AwaitingLandlord] as $status) {
        [$tenant, $case] = tenantOwning($status);
        $until = now()->addDays(7)->toDateString();

        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/hold", ['hold_until' => $until]);

        $response->assertRedirect("/cases/{$case->url_slug}");
        $case->refresh();
        expect($case->status)->toBe(CaseStatus::OnHold);
        expect($case->hold_until?->toDateString())->toBe($until);
    }
});

it('hold returns 403 from invalid states (e.g. open, on_hold, terminals, dormant)', function () {
    foreach ([CaseStatus::Open, CaseStatus::OnHold, CaseStatus::Dormant, CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        [$tenant, $case] = tenantOwning($status);

        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/hold", [
            'hold_until' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

it('hold rejects a hold_until in the past or today', function () {
    [$tenant, $case] = tenantOwning(CaseStatus::AwaitingTenantReview);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/hold", [
        'hold_until' => now()->subDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('hold_until');
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
});

it('hold rejects a hold_until beyond hold.max_days', function () {
    [$tenant, $case] = tenantOwning(CaseStatus::AwaitingTenantReview);

    // Default hold.max_days = 60.
    $tooFar = now()->addDays(120)->toDateString();
    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/hold", [
        'hold_until' => $tooFar,
    ]);

    $response->assertSessionHasErrors('hold_until');
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
});

it('hold returns 403 when a different tenant attempts the action', function () {
    [, $case] = tenantOwning(CaseStatus::AwaitingTenantReview);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/cases/{$case->url_slug}/hold", [
        'hold_until' => now()->addDays(7)->toDateString(),
    ]);

    $response->assertForbidden();
});

// ---------- resolve ----------

it('resolve closes the case from any non-terminal active state including dormant', function () {
    foreach ([CaseStatus::AwaitingLandlord, CaseStatus::AwaitingTenantReview, CaseStatus::OnHold, CaseStatus::Dormant] as $status) {
        [$tenant, $case] = tenantOwning($status);

        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/resolve");

        $response->assertRedirect("/cases/{$case->url_slug}");
        $case->refresh();
        expect($case->status)->toBe(CaseStatus::Resolved);
        expect($case->closed_at)->not->toBeNull();
    }
});

it('resolve returns 403 from terminal states', function () {
    foreach ([CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        [$tenant, $case] = tenantOwning($status);
        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/resolve");
        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

// ---------- abandon ----------

it('abandon closes the case and stores reason in event meta', function () {
    [$tenant, $case] = tenantOwning(CaseStatus::AwaitingLandlord);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/abandon", [
        'reason' => 'Repair done privately, no further pursuit needed.',
    ]);

    $response->assertRedirect("/cases/{$case->url_slug}");
    $case->refresh();
    expect($case->status)->toBe(CaseStatus::Abandoned);
    expect($case->closed_at)->not->toBeNull();

    $event = $case->events()->where('event_type', 'case_abandoned')->first();
    expect($event)->not->toBeNull();
    expect($event->meta['reason'] ?? null)->toBe('Repair done privately, no further pursuit needed.');
});

it('abandon works without a reason', function () {
    [$tenant, $case] = tenantOwning(CaseStatus::AwaitingTenantReview);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/abandon");

    $response->assertRedirect("/cases/{$case->url_slug}");
    expect($case->fresh()->status)->toBe(CaseStatus::Abandoned);
});

it('abandon returns 403 from terminal states', function () {
    foreach ([CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        [$tenant, $case] = tenantOwning($status);
        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/abandon");
        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

// ---------- guest redirects ----------

it('action routes redirect guests to login', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    foreach (['hold', 'resolve', 'abandon', 'reply'] as $action) {
        $response = $this->post("/cases/{$case->url_slug}/{$action}", [
            'hold_until' => now()->addDay()->toDateString(),
            'body' => 'placeholder reply body',
        ]);
        $response->assertRedirect('/login');
    }
});
