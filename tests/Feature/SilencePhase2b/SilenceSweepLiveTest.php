<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\CaseMessage;
use App\Models\LandlordContact;
use App\Models\LetterTemplate;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\SilenceShadowLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const PHASE2B_SNAPSHOT = [
    'escalation.interval_days' => 14,
    'escalation.max_notices' => 4,
    'nudge.first_days' => 10,
    'nudge.second_days' => 20,
    'nudge.dormancy_days' => 30,
];

function landlordSideExpiredCase(int $lettersAlreadySent = 1, int $daysAgo = 15): RepairCase
{
    $tenant = User::factory()->create();
    $contact = LandlordContact::factory()->create(['name' => 'Mr Landlord']);
    $property = Property::factory()->create([
        'address_line1' => '12 Test Street',
        'postcode' => 'SW1A 1AA',
    ]);
    $category = RepairCategory::factory()->create();

    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'landlord_contact_id' => $contact->id,
        'property_id' => $property->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => $lettersAlreadySent,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::now()->subDays($daysAgo),
        'silence_settings_snapshot' => PHASE2B_SNAPSHOT,
    ]);

    for ($i = 1; $i <= $lettersAlreadySent; $i++) {
        CaseMessage::factory()->create([
            'case_id' => $case->id,
            'direction' => MessageDirection::Outbound,
            'sender_role' => SenderRole::System,
            'stage_at_send' => $i,
        ]);
    }

    return $case->fresh();
}

// ─── Live execution: landlord-side send_escalation ─────────────────

it('executes the send for a landlord-side send_escalation verdict — queues mail, creates case_messages row', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();
    $messagesBefore = $case->messages()->count();

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertQueued(CaseNotice::class);
    expect($case->messages()->count())->toBe($messagesBefore + 1);
    $newMessage = $case->messages()->orderByDesc('id')->first();
    expect($newMessage->stage_at_send)->toBe(2);
    expect($newMessage->direction)->toBe(MessageDirection::Outbound);
    expect($newMessage->sender_role)->toBe(SenderRole::System);
});

it('restarts the silence clock and refreshes the snapshot after auto-escalation', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();
    $clockBefore = $case->silence_clock_started_at;

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->silence_clock_started_at->gt($clockBefore))->toBeTrue();
    expect($case->silence_settings_snapshot)->toHaveKey('escalation.interval_days', 14);
});

it('marks the shadow row executed=true when the send fires', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();

    $this->artisan('silence:sweep')->assertSuccessful();

    $row = SilenceShadowLog::query()->where('case_id', $case->id)->sole();
    expect($row->executed)->toBeTrue();
    expect($row->intended_action)->toBe('send_escalation');
});

it('writes an auto_escalation_sent event on the case (not notice_sent, not stage_advanced)', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();

    $this->artisan('silence:sweep')->assertSuccessful();

    $events = $case->events()->orderByDesc('id')->pluck('event_type')->all();
    expect($events)->toContain('auto_escalation_sent');
    expect($events)->toContain('token_issued');
    // tenant-initiated escalation markers must NOT appear for auto-fires
    expect($events)->not->toContain('escalation_confirmed_by_tenant');
    expect($events)->not->toContain('stage_advanced');
});

it('does NOT transition the case — status remains awaiting_landlord, current_stage advances on the row', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();

    $this->artisan('silence:sweep')->assertSuccessful();

    $fresh = $case->fresh();
    expect($fresh->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($fresh->current_stage)->toBe(2);
});

// ─── Tenant notification (active-row idiom) ────────────────────────

it('queues the AutoEscalationTenantNotice to the tenant when the active template exists', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertQueued(AutoEscalationTenantNotice::class, function (AutoEscalationTenantNotice $mail) use ($case) {
        return $mail->hasTo($case->tenant->email);
    });
});

it('skips the tenant notification silently when no active row of type tenant_notification with the code exists', function () {
    Mail::fake();
    LetterTemplate::query()
        ->where('code', 'auto_escalation_tenant_notice')
        ->update(['active' => false]);

    $case = landlordSideExpiredCase();

    $this->artisan('silence:sweep')->assertSuccessful();

    // Landlord-side send still fires; only the tenant notification is gated.
    Mail::assertQueued(CaseNotice::class);
    Mail::assertNotQueued(AutoEscalationTenantNotice::class);
});

it('does NOT write a case_messages row for the tenant notification (ruling-d invariant)', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();

    $messagesBefore = CaseMessage::query()->count();

    $this->artisan('silence:sweep')->assertSuccessful();

    // Exactly one new case_messages row from the landlord send;
    // none from the tenant notification.
    expect(CaseMessage::query()->count())->toBe($messagesBefore + 1);
});

// ─── Exhaustion (counter >= max_notices) ──────────────────────────

it('logs transition_exhausted_intent and sends NOTHING at counter >= max', function () {
    Mail::fake();
    $case = landlordSideExpiredCase(lettersAlreadySent: 4);

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertNothingQueued();
    $row = SilenceShadowLog::query()->where('case_id', $case->id)->sole();
    expect($row->intended_action)->toBe('transition_exhausted_intent');
    expect($row->executed)->toBeFalse();
});

// ─── Tenant-side stays shadow ─────────────────────────────────────

it('tenant-side nudge verdicts now EXECUTE in Phase 3 (live; mail queued, nudge_sent event written)', function () {
    Mail::fake();
    $contact = LandlordContact::factory()->create();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingTenantReview,
        'ball_with' => 'tenant',
        'silence_clock_started_at' => Carbon::now()->subDays(15),
        'silence_settings_snapshot' => PHASE2B_SNAPSHOT,
    ]);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertQueued(\App\Mail\Notifications\AutoEscalationTenantNotice::class);
    $row = SilenceShadowLog::query()->where('case_id', $case->id)->sole();
    expect($row->intended_action)->toBe('send_nudge');
    expect($row->executed)->toBeTrue();
    expect($case->events()->where('event_type', 'nudge_sent')->count())->toBe(1);
    // Evidential invariant: nudges never write a case_messages row.
    expect($case->messages()->where('direction', \App\Enums\MessageDirection::Outbound)->count())->toBe(0);
});

// ─── Pretend forces full shadow ───────────────────────────────────

it('pretend mode does NOT execute even when the verdict would be send_escalation', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
    $case = landlordSideExpiredCase(daysAgo: 5);  // not expired today; +15 → expired

    $future = Carbon::now()->addDays(15)->toDateString();
    $this->artisan("silence:sweep --pretend-today={$future}")->assertSuccessful();

    Mail::assertNothingQueued();
    $row = SilenceShadowLog::query()->where('case_id', $case->id)->sole();
    expect($row->intended_action)->toBe('send_escalation');
    expect($row->executed)->toBeFalse();
    expect($row->is_pretend)->toBeTrue();
});

// ─── Double-fire / race guard ──────────────────────────────────────

it('a second sweep in the same minute does NOT double-send — only one case_messages row is created', function () {
    Mail::fake();
    $case = landlordSideExpiredCase();

    $this->artisan('silence:sweep')->assertSuccessful();
    $this->artisan('silence:sweep')->assertSuccessful();

    $newMessages = $case->messages()
        ->where('direction', MessageDirection::Outbound)
        ->where('sender_role', SenderRole::System)
        ->where('stage_at_send', 2)
        ->count();
    expect($newMessages)->toBe(1);

    expect(Mail::queued(CaseNotice::class)->count())->toBe(1);
});

// ─── Per-case exception isolation (ruling f) ──────────────────────

it('a case whose send throws does NOT prevent subsequent cases being evaluated (ruling f)', function () {
    // Per-case exception isolation: a throw inside one case's handling
    // must not abort the sweep — the sweep catches, writes an exception
    // shadow row for that case, and continues.
    //
    // Trigger: clear MAILGUN_CASES_FROM_ADDRESS just before the sweep,
    // which makes CaseNotice's constructor throw a RuntimeException
    // when SendCaseNotice attempts to construct the mailable. Both
    // cases throw — the proof of isolation is that BOTH get exception
    // shadow rows. A pre-ruling-f sweep without isolation would have
    // aborted after the first throw, leaving the second case unrow'd.
    Mail::fake();
    $firstCase = landlordSideExpiredCase();
    $secondCase = landlordSideExpiredCase();

    config(['services.mailgun.cases_from_address' => '']);

    $this->artisan('silence:sweep')->assertSuccessful();

    foreach ([$firstCase, $secondCase] as $case) {
        $row = SilenceShadowLog::query()->where('case_id', $case->id)->latest('id')->first();
        expect($row)->not->toBeNull();
        expect($row->executed)->toBeFalse();
        expect($row->reasoning)->toContain('exception during sweep');
        expect($row->reasoning)->toContain('RuntimeException');
    }

    // No mail queued because both sends threw before reaching the queue.
    Mail::assertNotQueued(CaseNotice::class);
});
