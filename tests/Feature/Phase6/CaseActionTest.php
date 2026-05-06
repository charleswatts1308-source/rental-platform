<?php

use App\Enums\CaseStatus;
use App\Mail\CaseNotice;
use App\Models\LandlordContact;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function tenantOwning(CaseStatus $status, array $overrides = []): array
{
    $tenant = User::factory()->create();
    $contact = LandlordContact::factory()->create();
    $case = RepairCase::factory()->create(array_merge([
        'tenant_user_id' => $tenant->id,
        'landlord_contact_id' => $contact->id,
        'status' => $status,
    ], $overrides));

    return [$tenant, $case];
}

// ---------- send-next ----------

it('send-next dispatches the next notice from tenant_action_required', function () {
    [$tenant, $case] = tenantOwning(CaseStatus::TenantActionRequired, ['current_stage' => 1]);
    ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $case->landlordContact->email,
        'superseded_at' => null,
    ]);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/send-next");

    $response->assertRedirect("/cases/{$case->url_slug}");
    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->current_stage)->toBe(2);
    Mail::assertQueued(CaseNotice::class);
});

it('send-next returns 403 from any state other than tenant_action_required', function () {
    foreach ([CaseStatus::AwaitingLandlord, CaseStatus::AwaitingTenantReview, CaseStatus::OnHold, CaseStatus::Dormant, CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        [$tenant, $case] = tenantOwning($status);
        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/send-next");
        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

it('send-next returns 403 when a different tenant attempts the action', function () {
    [, $case] = tenantOwning(CaseStatus::TenantActionRequired);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/cases/{$case->url_slug}/send-next");

    $response->assertForbidden();
});

// ---------- hold ----------

it('hold pauses the case from awaiting_tenant_review and tenant_action_required', function () {
    foreach ([CaseStatus::AwaitingTenantReview, CaseStatus::TenantActionRequired] as $status) {
        [$tenant, $case] = tenantOwning($status);
        $until = now()->addDays(7)->toDateString();

        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/hold", ['hold_until' => $until]);

        $response->assertRedirect("/cases/{$case->url_slug}");
        $case->refresh();
        expect($case->status)->toBe(CaseStatus::OnHold);
        expect($case->hold_until?->toDateString())->toBe($until);
    }
});

it('hold returns 403 from invalid states (e.g. awaiting_landlord, on_hold, terminals)', function () {
    foreach ([CaseStatus::AwaitingLandlord, CaseStatus::OnHold, CaseStatus::Dormant, CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        [$tenant, $case] = tenantOwning($status);

        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/hold", [
            'hold_until' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

it('hold rejects a hold_until in the past or today', function () {
    [$tenant, $case] = tenantOwning(CaseStatus::TenantActionRequired);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/hold", [
        'hold_until' => now()->subDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('hold_until');
    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('hold returns 403 when a different tenant attempts the action', function () {
    [, $case] = tenantOwning(CaseStatus::TenantActionRequired);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/cases/{$case->url_slug}/hold", [
        'hold_until' => now()->addDays(7)->toDateString(),
    ]);

    $response->assertForbidden();
});

// ---------- resolve ----------

it('resolve closes the case from any non-terminal active state', function () {
    foreach ([CaseStatus::AwaitingLandlord, CaseStatus::AwaitingTenantReview, CaseStatus::TenantActionRequired, CaseStatus::OnHold] as $status) {
        [$tenant, $case] = tenantOwning($status);

        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/resolve");

        $response->assertRedirect("/cases/{$case->url_slug}");
        $case->refresh();
        expect($case->status)->toBe(CaseStatus::Resolved);
        expect($case->closed_at)->not->toBeNull();
    }
});

it('resolve returns 403 from dormant and terminal states', function () {
    foreach ([CaseStatus::Dormant, CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
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
    [$tenant, $case] = tenantOwning(CaseStatus::TenantActionRequired);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/abandon");

    $response->assertRedirect("/cases/{$case->url_slug}");
    expect($case->fresh()->status)->toBe(CaseStatus::Abandoned);
});

it('abandon returns 403 from dormant (admin-only) and terminal states', function () {
    foreach ([CaseStatus::Dormant, CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        [$tenant, $case] = tenantOwning($status);
        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/abandon");
        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

// ---------- re-engage ----------

it('re-engage transitions a dormant case to tenant_action_required and writes tenant_re_engaged', function () {
    [$tenant, $case] = tenantOwning(CaseStatus::Dormant);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/re-engage");

    $response->assertRedirect("/cases/{$case->url_slug}");
    $case->refresh();
    expect($case->status)->toBe(CaseStatus::TenantActionRequired);
    expect($case->events()->where('event_type', 'tenant_re_engaged')->count())->toBe(1);
});

it('re-engage returns 403 from any state other than dormant', function () {
    foreach ([CaseStatus::AwaitingLandlord, CaseStatus::AwaitingTenantReview, CaseStatus::TenantActionRequired, CaseStatus::OnHold, CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        [$tenant, $case] = tenantOwning($status);
        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/re-engage");
        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

it('re-engage returns 403 when a different tenant attempts the action', function () {
    [, $case] = tenantOwning(CaseStatus::Dormant);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/cases/{$case->url_slug}/re-engage");

    $response->assertForbidden();
    expect($case->fresh()->status)->toBe(CaseStatus::Dormant);
});

// ---------- guest redirects ----------

it('action routes redirect guests to login', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::TenantActionRequired]);

    foreach (['send-next', 'hold', 'resolve', 'abandon', 're-engage'] as $action) {
        $response = $this->post("/cases/{$case->url_slug}/{$action}", ['hold_until' => now()->addDay()->toDateString()]);
        $response->assertRedirect('/login');
    }
});
