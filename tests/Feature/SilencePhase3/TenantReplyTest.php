<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Models\LandlordContact;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function caseFor(User $tenant, CaseStatus $status, array $overrides = []): RepairCase
{
    static $n = 0;
    $n++;
    $contact = LandlordContact::factory()->create(['email' => "landlord{$n}@example.com"]);
    $category = RepairCategory::factory()->create();

    return RepairCase::factory()->create(array_merge([
        'tenant_user_id' => $tenant->id,
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'description' => 'Damp in the bedroom.',
        'status' => $status,
        'current_stage' => 1,
    ], $overrides));
}

// ─── D8 availability table ────────────────────────────────────────

it('allows reply from awaiting_tenant_review', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingTenantReview);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Thanks — please book the inspection this week.',
    ]);

    $response->assertRedirect("/cases/{$case->url_slug}");
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('allows reply from awaiting_landlord (self-target add-info)', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingLandlord);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Adding photos of the new patch.',
    ]);

    $response->assertRedirect("/cases/{$case->url_slug}");
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('allows reply from on_hold (resume action)', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::OnHold, ['hold_until' => now()->addDays(14)]);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Unpausing — issue persists.',
    ]);

    $response->assertRedirect("/cases/{$case->url_slug}");
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('allows reply from dormant within the revival window', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::Dormant, [
        'dormant_at' => now()->subDays(30),
    ]);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Sorry — issue is back, can we continue.',
    ]);

    $response->assertRedirect("/cases/{$case->url_slug}");
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->fresh()->dormant_at)->toBeNull();
});

it('forbids reply from dormant beyond the revival window', function () {
    $tenant = User::factory()->create();
    Setting::query()->updateOrInsert(['key' => 'dormancy.revival_days'], ['value' => '90']);
    $case = caseFor($tenant, CaseStatus::Dormant, [
        'dormant_at' => now()->subDays(120),
    ]);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Late reply attempt.',
    ]);

    $response->assertForbidden();
    expect($case->fresh()->status)->toBe(CaseStatus::Dormant);
});

it('forbids reply from resolved and abandoned', function () {
    $tenant = User::factory()->create();
    foreach ([CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        $case = caseFor($tenant, $status, ['closed_at' => now()->subDay()]);

        $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
            'body' => 'Trying to revive.',
        ]);

        $response->assertForbidden();
        expect($case->fresh()->status)->toBe($status);
    }
});

it('forbids reply from open (no first send yet)', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::Open);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Trying to bypass first send.',
    ]);

    $response->assertForbidden();
    expect($case->fresh()->status)->toBe(CaseStatus::Open);
});

it('forbids reply from a different tenant', function () {
    $tenant = User::factory()->create();
    $other = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingLandlord);

    $response = $this->actingAs($other)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'I should not be able to do this.',
    ]);

    $response->assertForbidden();
});

it('validates body is required and non-empty', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingTenantReview);

    $response = $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => '',
    ]);

    $response->assertSessionHasErrors('body');
});

// ─── Outbound shape + token + clock ───────────────────────────────

it('writes a tenant-sender outbound case_messages row with stage_at_send null', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingTenantReview);

    $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Some reply text — verbatim and frozen.',
    ]);

    $message = $case->messages()
        ->where('direction', MessageDirection::Outbound)
        ->sole();
    expect($message->sender_role)->toBe(SenderRole::Tenant);
    expect($message->stage_at_send)->toBeNull();
    expect($message->letter_template_id)->toBeNull();
    expect($message->body_raw)->toContain('Some reply text — verbatim and frozen.');
    // D9 header block on every outbound mail.
    expect($message->body_raw)->toContain('Damp in the bedroom.');
});

it('queues CaseNotice mailable to the landlord on reply', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingTenantReview);

    $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Pls hurry.',
    ]);

    Mail::assertQueued(CaseNotice::class);
});

it('mints a fresh token and supersedes the old one on reply', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingTenantReview);
    \App\Models\ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $case->landlordContact->email,
        'superseded_at' => null,
    ]);

    $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Pls hurry.',
    ]);

    expect($case->replyTokens()->whereNull('superseded_at')->count())->toBe(1);
    expect($case->replyTokens()->whereNotNull('superseded_at')->count())->toBe(1);
});

it('restarts the silence clock (ball→landlord, snapshot refresh)', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingTenantReview, [
        'ball_with' => 'tenant',
        'silence_clock_started_at' => now()->subDays(5),
    ]);

    $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Reply.',
    ]);

    $fresh = $case->fresh();
    expect($fresh->ball_with)->toBe('landlord');
    expect($fresh->silence_clock_started_at->greaterThan(now()->subMinute()))->toBeTrue();
    expect($fresh->silence_settings_snapshot)->toHaveKey('escalation.interval_days');
});

it('writes tenant_replied as the canonical event when transitioning', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingTenantReview);

    $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Pls hurry.',
    ]);

    expect($case->events()->where('event_type', 'tenant_replied')->count())->toBe(1);
});

it('writes tenant_replied explicitly on the self-send branch (AwaitingLandlord)', function () {
    $tenant = User::factory()->create();
    $case = caseFor($tenant, CaseStatus::AwaitingLandlord);

    $this->actingAs($tenant)->post("/cases/{$case->url_slug}/reply", [
        'body' => 'Adding info.',
    ]);

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->events()->where('event_type', 'tenant_replied')->count())->toBe(1);
});
