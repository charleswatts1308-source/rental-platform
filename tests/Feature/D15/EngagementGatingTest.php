<?php

use App\Enums\BallPosition;
use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\CaseMessage;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Models\User;
use App\Services\Silence\IntendedAction;
use App\Services\Silence\SilenceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const D15_SNAPSHOT = [
    'escalation.interval_days' => 14,
    'escalation.max_notices' => 4,
    'nudge.first_days' => 10,
    'nudge.second_days' => 20,
    'nudge.dormancy_days' => 30,
];

beforeEach(function () {
    Mail::fake();
});

/**
 * A landlord-ball case in awaiting_landlord with the escalation clock
 * expired. $engaged sets the D15 flag; $lettersSent controls the counter
 * (outbound system rows with stage_at_send). The last message is outbound
 * so ballFor() => Landlord.
 */
function d15LandlordBallCase(bool $engaged, int $lettersSent = 1, int $daysAgo = 15): RepairCase
{
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['address_line1' => '12 Test Street', 'postcode' => 'SW1A 1AA']);
    $category = RepairCategory::factory()->create();

    $case = RepairCase::factory()->withLandlord(['name' => 'Mr Landlord'])->create([
        'tenant_user_id' => $tenant->id,
        'property_id' => $property->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => $lettersSent,
        'ball_with' => 'landlord',
        'landlord_engaged' => $engaged,
        'silence_clock_started_at' => Carbon::now()->subDays($daysAgo),
        'silence_settings_snapshot' => D15_SNAPSHOT,
    ]);

    for ($i = 1; $i <= $lettersSent; $i++) {
        CaseMessage::factory()->create([
            'case_id' => $case->id,
            'direction' => MessageDirection::Outbound,
            'sender_role' => SenderRole::System,
            'stage_at_send' => $i,
        ]);
    }

    return $case->fresh();
}

function escalationLetterCount(RepairCase $case): int
{
    return $case->messages()
        ->where('direction', MessageDirection::Outbound)
        ->where('sender_role', SenderRole::System)
        ->whereNotNull('stage_at_send')
        ->count();
}

// ─── D0.1 — the engagement flag flips on inbound, idempotent, never resets ───

function d15InboundPayload(string $token, array $overrides = []): array
{
    $key = (string) config('services.mailgun.webhook_signing_key');
    $ts = (string) time();
    $sigToken = str_repeat('a', 50);

    return array_merge([
        'timestamp' => $ts,
        'token' => $sigToken,
        'signature' => hash_hmac('sha256', $ts.$sigToken, $key),
        'recipient' => $token.'@'.config('services.mailgun.inbound_domain'),
        'from' => 'Mr Landlord <landlord@example.com>',
        'subject' => 'Re: repair issue',
        'body-plain' => 'I will look at it.',
        'Message-Id' => '<x@mg.example.com>',
    ], $overrides);
}

function d15CaseWithToken(string $landlordEmail = 'landlord@example.com'): array
{
    $case = RepairCase::factory()->withLandlord(['email' => $landlordEmail])->create([
        'status' => CaseStatus::AwaitingLandlord,
        'landlord_engaged' => false,
    ]);
    $token = ReplyToken::factory()->create([
        'case_id' => $case->id,
        'token' => Str::random(20),
        'bound_email' => $case->landlordRecipient()->email,
        'superseded_at' => null,
    ]);

    return [$case->fresh(), $token];
}

it('flips landlord_engaged true on a genuine inbound landlord reply', function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key');
    [$case, $token] = d15CaseWithToken();
    expect($case->landlord_engaged)->toBeFalse();

    $this->post('/webhooks/mailgun/inbound', d15InboundPayload($token->token))->assertStatus(200);

    expect($case->fresh()->landlord_engaged)->toBeTrue();
});

it('flips landlord_engaged true even on a quarantined (from-mismatch) inbound — ruling 1, fails safe', function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key');
    [$case, $token] = d15CaseWithToken('landlord@example.com');

    // From a different address → quarantined, but still token-resolved.
    $this->post('/webhooks/mailgun/inbound', d15InboundPayload($token->token, [
        'from' => 'Someone Else <stranger@elsewhere.com>',
    ]))->assertStatus(200);

    $case->refresh();
    expect($case->landlord_engaged)->toBeTrue();
    $msg = $case->messages()->where('direction', MessageDirection::Inbound)->sole();
    expect($msg->quarantine_reason)->not->toBeNull();
});

it('is idempotent and never resets — a second inbound leaves the flag true', function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key');
    [$case, $token] = d15CaseWithToken();

    $this->post('/webhooks/mailgun/inbound', d15InboundPayload($token->token))->assertStatus(200);
    $this->post('/webhooks/mailgun/inbound', d15InboundPayload($token->token))->assertStatus(200);

    expect($case->fresh()->landlord_engaged)->toBeTrue();
});

// ─── D0.2 — verdict branch ───

it('never-engaged landlord-ball case still produces SendEscalation on expiry', function () {
    $case = d15LandlordBallCase(engaged: false);
    $verdict = app(SilenceClock::class)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::SendEscalation);
    expect($verdict->ballWith)->toBe(BallPosition::Landlord);
});

it('engaged landlord-ball case WITHHOLDS — produces SendAuthorisationNudge, not SendEscalation', function () {
    $case = d15LandlordBallCase(engaged: true);
    $verdict = app(SilenceClock::class)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::SendAuthorisationNudge);
    expect($verdict->ballWith)->toBe(BallPosition::Landlord);
    expect($verdict->nudgeNumber)->toBe(1);
});

// ─── never-engaged auto-escalates AND notifies (D0.6 confirm) ───

it('never-engaged case auto-escalates on landlord silence and notifies the tenant', function () {
    $case = d15LandlordBallCase(engaged: false);
    $before = escalationLetterCount($case);

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertQueued(CaseNotice::class);              // landlord letter
    Mail::assertQueued(AutoEscalationTenantNotice::class); // tenant heartbeat
    expect(escalationLetterCount($case->fresh()))->toBe($before + 1);
});

// ─── engaged-then-quiet WITHHOLDS + nudges the tenant ───

it('engaged-then-quiet case withholds escalation and fires an authorise-nudge (mail only, no case_messages row)', function () {
    $case = d15LandlordBallCase(engaged: true);
    $before = escalationLetterCount($case);

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertNotQueued(CaseNotice::class);              // NO landlord letter
    Mail::assertQueued(AutoEscalationTenantNotice::class);  // tenant authorise-nudge
    expect(escalationLetterCount($case->fresh()))->toBe($before); // counter untouched
    expect($case->events()->where('event_type', 'authorisation_nudge_sent')->count())->toBe(1);
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('does not restart the clock on an authorise-nudge — silence keeps accruing', function () {
    $case = d15LandlordBallCase(engaged: true);
    $clockBefore = $case->silence_clock_started_at->copy();

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->fresh()->silence_clock_started_at->equalTo($clockBefore))->toBeTrue();
});

it('the authorise-nudge shows the LANDLORD last-reply date, not a later tenant reply date', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create();
    $category = RepairCategory::factory()->create();

    $landlordReplyDate = Carbon::now()->subDays(25); // landlord's last inbound
    $tenantReplyDate = Carbon::now()->subDays(15);   // tenant's later "thanks"

    $case = RepairCase::factory()->withLandlord(['name' => 'Mr Landlord'])->create([
        'tenant_user_id' => $tenant->id,
        'property_id' => $property->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => 1,
        'ball_with' => 'landlord',
        'landlord_engaged' => true,
        'silence_clock_started_at' => $tenantReplyDate->copy(),
        'silence_settings_snapshot' => D15_SNAPSHOT,
    ]);

    // notice 1 (outbound system) → counter = 1
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => 1,
        'sent_at' => Carbon::now()->subDays(30),
    ]);
    // landlord reply (inbound) — the date the nudge must show
    CaseMessage::factory()->inbound()->create([
        'case_id' => $case->id,
        'received_at' => $landlordReplyDate,
    ]);
    // tenant "thanks" reply (outbound tenant) — the LATEST message; must NOT be shown
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::Tenant,
        'stage_at_send' => null,
        'sent_at' => $tenantReplyDate,
    ]);

    $this->artisan('silence:sweep')->assertSuccessful();

    $landlordStr = $landlordReplyDate->format('d M Y');
    $tenantStr = $tenantReplyDate->format('d M Y');
    expect($landlordStr)->not->toBe($tenantStr); // guard the fixture

    Mail::assertQueued(AutoEscalationTenantNotice::class, function ($mail) use ($landlordStr, $tenantStr) {
        return str_contains($mail->renderedBody, $landlordStr)
            && ! str_contains($mail->renderedBody, $tenantStr);
    });
});

// ─── tenant authorise fires the held notice through the outbound path ───

it('tenant authorise fires the withheld notice: counter ratchets, letter frozen, clock restarts', function () {
    $case = d15LandlordBallCase(engaged: true);
    $before = escalationLetterCount($case);
    $clockBefore = $case->silence_clock_started_at->copy();

    $this->actingAs($case->tenant)
        ->post(route('cases.escalate.authorise', $case->url_slug))
        ->assertRedirect(route('cases.show', $case->url_slug));

    $case->refresh();
    Mail::assertQueued(CaseNotice::class);
    expect(escalationLetterCount($case))->toBe($before + 1);
    expect($case->current_stage)->toBe(2);
    expect($case->silence_clock_started_at->greaterThan($clockBefore))->toBeTrue();
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('the authorise preview renders for a held case and is denied when not held', function () {
    $held = d15LandlordBallCase(engaged: true);
    $this->actingAs($held->tenant)
        ->get(route('cases.escalate.preview', $held->url_slug))
        ->assertOk();

    // Not expired yet → not held → denied.
    $notYet = d15LandlordBallCase(engaged: true, daysAgo: 3);
    $this->actingAs($notYet->tenant)
        ->get(route('cases.escalate.preview', $notYet->url_slug))
        ->assertForbidden();

    // Never-engaged → not held → denied (it would just auto-escalate).
    $auto = d15LandlordBallCase(engaged: false);
    $this->actingAs($auto->tenant)
        ->post(route('cases.escalate.authorise', $auto->url_slug))
        ->assertForbidden();
});

// ─── unauthorised engaged-quiet falls to the existing dormancy tail ───

it('unauthorised engaged-quiet case goes dormant via the new awaiting_landlord -> dormant edge', function () {
    $case = d15LandlordBallCase(engaged: true, daysAgo: 35);
    // Both authorise-nudges already sent within this clock window.
    foreach ([1, 2] as $n) {
        $case->events()->create([
            'event_type' => 'authorisation_nudge_sent',
            'actor_label' => 'system',
            'occurred_at' => now()->subDays(30 - $n * 5),
            'meta' => ['nudge_number' => $n],
        ]);
    }

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::Dormant);
    expect($case->dormant_at)->not->toBeNull();
    expect(escalationLetterCount($case))->toBe(1); // no escalation ever fired
});

it('a dormant engaged case still revives via tenant reply within the window (D11 intact)', function () {
    // Built dormant directly (factory create bypasses the transition guard,
    // which only fires on status UPDATE). landlord_engaged stays true across
    // revival — the one-way flag never resets.
    $tenant = User::factory()->create();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->withLandlord([])->create([
        'tenant_user_id' => $tenant->id,
        'category_key' => $category->key,
        'status' => CaseStatus::Dormant,
        'landlord_engaged' => true,
        'dormant_at' => now()->subDays(5),
        'ball_with' => 'tenant',
        'silence_clock_started_at' => now()->subDays(40),
        'silence_settings_snapshot' => D15_SNAPSHOT,
    ]);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);

    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), ['body' => 'Actually still broken.'])
        ->assertRedirect();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->landlord_engaged)->toBeTrue();
});

// ─── HEADLINE REGRESSION — the §4 thank-you harm is closed ───

it('HEADLINE: a thank-you reply from awaiting_tenant_review on an ENGAGED case does NOT auto-escalate', function () {
    // Landlord replied (engaged), case sits awaiting_tenant_review, ball tenant.
    $tenant = User::factory()->create();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->withLandlord(['email' => 'landlord@example.com'])->create([
        'tenant_user_id' => $tenant->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingTenantReview,
        'current_stage' => 1,
        'ball_with' => 'tenant',
        'landlord_engaged' => true,
        'silence_clock_started_at' => Carbon::now()->subDay(),
        'silence_settings_snapshot' => D15_SNAPSHOT,
    ]);
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => 1,
    ]);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);

    // Tenant replies "thanks, all sorted".
    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), ['body' => 'Thanks, all sorted!'])
        ->assertRedirect();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
    $escalationsBefore = escalationLetterCount($case);

    // Time passes; the landlord (expectedly) stays silent past the interval.
    $case->update(['silence_clock_started_at' => Carbon::now()->subDays(20)]);
    Mail::fake();
    $this->artisan('silence:sweep')->assertSuccessful();

    // The harm is closed: NO escalation letter fired at the landlord.
    Mail::assertNotQueued(CaseNotice::class);
    expect(escalationLetterCount($case->fresh()))->toBe($escalationsBefore);
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});
