<?php

use App\Enums\CaseStatus;
use App\Enums\ExhaustedStance;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Models\SilenceShadowLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const D14_SNAPSHOT = [
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
 * A never-engaged, landlord-ball case in awaiting_landlord whose escalation
 * clock has expired and whose counter is at max_notices (4) — i.e. the
 * sweep's next verdict is TransitionExhaustedIntent. $engaged toggles the
 * D15 flag for the guard test.
 */
function d14ExhaustionEligibleCase(bool $engaged = false, int $counter = 4, int $daysAgo = 20): RepairCase
{
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['address_line1' => '12 Test Street', 'postcode' => 'SW1A 1AA']);
    $category = RepairCategory::factory()->create();

    $case = RepairCase::factory()->withLandlord(['name' => 'Mr Landlord', 'email' => 'landlord@example.com'])->create([
        'tenant_user_id' => $tenant->id,
        'property_id' => $property->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => $counter,
        'ball_with' => 'landlord',
        'landlord_engaged' => $engaged,
        'silence_clock_started_at' => Carbon::now()->subDays($daysAgo),
        'silence_settings_snapshot' => D14_SNAPSHOT,
    ]);

    for ($i = 1; $i <= $counter; $i++) {
        CaseMessage::factory()->create([
            'case_id' => $case->id,
            'direction' => MessageDirection::Outbound,
            'sender_role' => SenderRole::System,
            'stage_at_send' => $i,
        ]);
    }

    return $case->fresh();
}

/** A case already sitting in the escalation_exhausted terminal. */
function d14ExhaustedCase(?ExhaustedStance $stance = null): RepairCase
{
    $tenant = User::factory()->create();
    $category = RepairCategory::factory()->create();

    $case = RepairCase::factory()->withLandlord([])->create([
        'tenant_user_id' => $tenant->id,
        'category_key' => $category->key,
        'status' => CaseStatus::EscalationExhausted,
        'current_stage' => 4,
        'ball_with' => 'landlord',
        'landlord_engaged' => false,
        'exhausted_stance' => $stance,
        'silence_clock_started_at' => Carbon::now()->subDays(40),
        'silence_settings_snapshot' => D14_SNAPSHOT,
    ]);

    for ($i = 1; $i <= 4; $i++) {
        CaseMessage::factory()->create([
            'case_id' => $case->id,
            'direction' => MessageDirection::Outbound,
            'sender_role' => SenderRole::System,
            'stage_at_send' => $i,
        ]);
    }

    return $case->fresh();
}

function d14EscalationLetterCount(RepairCase $case): int
{
    return $case->messages()
        ->where('direction', MessageDirection::Outbound)
        ->where('sender_role', SenderRole::System)
        ->whereNotNull('stage_at_send')
        ->count();
}

function d14CloserMessageCount(RepairCase $case): int
{
    return $case->messages()
        ->where('direction', MessageDirection::Outbound)
        ->where('sender_role', SenderRole::System)
        ->whereNull('stage_at_send')
        ->count();
}

// ─── HEADLINE 1 — never-engaged at max transitions + fires the closer ───

it('HEADLINE: a never-engaged case at max_notices transitions to escalation_exhausted, fires the closer + tenant notice', function () {
    $case = d14ExhaustionEligibleCase();
    $counterBefore = d14EscalationLetterCount($case);

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::EscalationExhausted);

    // Closer fired to the landlord + tenant exhaustion notice queued.
    Mail::assertQueued(CaseNotice::class);
    Mail::assertQueued(AutoEscalationTenantNotice::class);

    // The closer is a System outbound row with stage_at_send=NULL — it does
    // NOT count toward the ladder (D3 ratchet stays at max).
    expect(d14CloserMessageCount($case))->toBe(1);
    expect(d14EscalationLetterCount($case))->toBe($counterBefore);

    $row = SilenceShadowLog::query()->where('case_id', $case->id)->sole();
    expect($row->intended_action)->toBe('transition_exhausted_intent');
    expect($row->executed)->toBeTrue();
});

// ─── HEADLINE 2 — an exhausted case is sweep-inert at all three stances ───

it('HEADLINE: an escalation_exhausted case is sweep-inert at every stance value', function (?ExhaustedStance $stance) {
    $case = d14ExhaustedCase($stance);
    $counterBefore = d14EscalationLetterCount($case);

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    // No escalation, no nudge, no transition, nothing sent.
    Mail::assertNothingQueued();
    expect($case->status)->toBe(CaseStatus::EscalationExhausted);
    expect(d14EscalationLetterCount($case))->toBe($counterBefore);
    // Excluded from the sweep query entirely — not even a shadow row.
    expect(SilenceShadowLog::query()->where('case_id', $case->id)->count())->toBe(0);
})->with([
    'unset' => null,
    'abandoned' => ExhaustedStance::Abandoned,
    'unresolved' => ExhaustedStance::Unresolved,
]);

// ─── allow-reply revival, BOTH edges ───

it('allow-reply: a TENANT web reply revives an exhausted case to awaiting_landlord (engaged stays false)', function () {
    $case = d14ExhaustedCase();
    $clockBefore = $case->silence_clock_started_at->copy();
    $tokensBefore = $case->replyTokens()->count();

    $this->actingAs($case->tenant)
        ->post(route('cases.reply', $case->url_slug), ['body' => 'Actually, still not fixed.'])
        ->assertRedirect();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->ball_with)->toBe('landlord');
    expect($case->landlord_engaged)->toBeFalse();              // tenant reply does NOT engage the landlord
    expect($case->silence_clock_started_at->greaterThan($clockBefore))->toBeTrue(); // clock restarted (D11 shape)
    expect($case->replyTokens()->count())->toBe($tokensBefore + 1); // fresh token minted
    // Frozen tenant message on the record.
    expect($case->messages()->where('sender_role', SenderRole::Tenant)->where('direction', MessageDirection::Outbound)->count())->toBe(1);
    Mail::assertQueued(CaseNotice::class);
});

it('allow-reply: a LANDLORD email reply revives an exhausted case to awaiting_tenant_review and flips landlord_engaged true', function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key');

    $case = d14ExhaustedCase();
    $token = ReplyToken::factory()->create([
        'case_id' => $case->id,
        'token' => Str::random(20),
        'bound_email' => $case->landlordRecipient()->email,
        'superseded_at' => null,
    ]);

    $ts = (string) time();
    $sigToken = str_repeat('a', 50);
    $payload = [
        'timestamp' => $ts,
        'token' => $sigToken,
        'signature' => hash_hmac('sha256', $ts.$sigToken, 'test-signing-key'),
        'recipient' => $token->token.'@'.config('services.mailgun.inbound_domain'),
        'from' => 'Mr Landlord <'.$case->landlordRecipient()->email.'>',
        'subject' => 'Re: repair issue',
        'body-plain' => 'Sorry for the delay — I will sort it.',
        'Message-Id' => '<x@mg.example.com>',
    ];

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($case->landlord_engaged)->toBeTrue();
    expect($case->ball_with)->toBe('tenant');
});

// ─── stance is label-only ───

it('the stance flag changes NO sweep outcome — pure label', function () {
    $unset = d14ExhaustedCase(null);
    $abandoned = d14ExhaustedCase(ExhaustedStance::Abandoned);
    $unresolved = d14ExhaustedCase(ExhaustedStance::Unresolved);

    $this->artisan('silence:sweep')->assertSuccessful();

    foreach ([$unset, $abandoned, $unresolved] as $case) {
        expect($case->fresh()->status)->toBe(CaseStatus::EscalationExhausted);
        expect(SilenceShadowLog::query()->where('case_id', $case->id)->count())->toBe(0);
    }
    Mail::assertNothingQueued();
});

it('setStance writes the label only, gated to the owner of an exhausted case', function () {
    $case = d14ExhaustedCase();

    // Owner can set it.
    $this->actingAs($case->tenant)
        ->post(route('cases.stance', $case->url_slug), ['stance' => 'abandoned'])
        ->assertRedirect();
    expect($case->fresh()->exhausted_stance)->toBe(ExhaustedStance::Abandoned);
    // Status untouched.
    expect($case->fresh()->status)->toBe(CaseStatus::EscalationExhausted);

    // Empty submission clears it.
    $this->actingAs($case->tenant)
        ->post(route('cases.stance', $case->url_slug), ['stance' => ''])
        ->assertRedirect();
    expect($case->fresh()->exhausted_stance)->toBeNull();

    // A stranger cannot.
    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->post(route('cases.stance', $case->url_slug), ['stance' => 'unresolved'])
        ->assertForbidden();
});

it('setStance is denied on a non-exhausted case', function () {
    $case = d14ExhaustionEligibleCase(); // awaiting_landlord, not yet exhausted
    $this->actingAs($case->tenant)
        ->post(route('cases.stance', $case->url_slug), ['stance' => 'abandoned'])
        ->assertForbidden();
});

// ─── guard: engaged never reaches escalation_exhausted ───

it('GUARD: an engaged case never reaches escalation_exhausted — it goes the D15 held route', function () {
    // Engaged, counter below max, clock expired → D15 withholds (authorise-
    // nudge), it does NOT climb to exhaustion.
    $case = d14ExhaustionEligibleCase(engaged: true, counter: 1);

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->not->toBe(CaseStatus::EscalationExhausted);
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord); // held, awaiting authorisation
    Mail::assertNotQueued(CaseNotice::class); // no closer, no escalation
});

// ─── closer is one-shot across a re-exhaustion ───

it('the landlord closer is ONE-SHOT: a tenant-web revival that re-exhausts does not re-fire it', function () {
    $case = d14ExhaustionEligibleCase();

    // First exhaustion → closer #1.
    $this->artisan('silence:sweep')->assertSuccessful();
    expect($case->fresh()->status)->toBe(CaseStatus::EscalationExhausted);
    expect(d14CloserMessageCount($case->fresh()))->toBe(1);

    // Tenant revives via web → awaiting_landlord.
    $this->actingAs($case->tenant)
        ->post(route('cases.reply', $case->url_slug), ['body' => 'Still broken.'])
        ->assertRedirect();
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);

    // Landlord stays silent past the interval; re-exhaust.
    $case->fresh()->update(['silence_clock_started_at' => Carbon::now()->subDays(20)]);
    Mail::fake();
    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::EscalationExhausted);
    // No second closer — still exactly one.
    expect(d14CloserMessageCount($case->fresh()))->toBe(1);
    Mail::assertNotQueued(CaseNotice::class);
});

// ─── active-row idiom: no template → transition still happens, mail skipped ───

it('transitions even when no exhaustion templates are active — mail skipped silently', function () {
    LetterTemplate::query()->whereIn('code', ['exhaustion_landlord_closer', 'tenant_exhaustion_notice'])
        ->update(['active' => false]);

    $case = d14ExhaustionEligibleCase();

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::EscalationExhausted);
    Mail::assertNotQueued(CaseNotice::class);              // no active closer row
    Mail::assertNotQueued(AutoEscalationTenantNotice::class); // no active tenant notice row
    // No closer message row created.
    expect(d14CloserMessageCount($case->fresh()))->toBe(0);
});
