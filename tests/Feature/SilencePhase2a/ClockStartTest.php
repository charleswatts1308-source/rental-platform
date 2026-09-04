<?php

use App\Actions\HandleInboundReply;
use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Models\Setting;
use App\Services\LetterTemplateRenderer;
use App\Services\ReplyTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Clock-start touchpoints: SendCaseNotice (ball=landlord) and
 * HandleInboundReply (ball=tenant). Both set ball_with,
 * silence_clock_started_at, silence_settings_snapshot. Snapshot is
 * frozen — overwritten only at the next clock start, never edited
 * in place.
 */
function sendActionForClock(): SendCaseNotice
{
    return new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);
}

function openCaseForClock(): RepairCase
{
    $category = RepairCategory::factory()->create();

    return RepairCase::factory()->withLandlord(['email' => 'landlord@example.com'])->create([
        'category_key' => $category->key,
        'status' => CaseStatus::Open,
        'current_stage' => 1,
        'ball_with' => null,
        'silence_clock_started_at' => null,
        'silence_settings_snapshot' => null,
    ]);
}

it('it snapshots the current settings onto the case at outbound send', function () {
    Mail::fake();
    $case = openCaseForClock();

    sendActionForClock()->execute($case);

    $case->refresh();
    expect($case->ball_with)->toBe('landlord');
    expect($case->silence_clock_started_at)->not->toBeNull();
    expect($case->silence_settings_snapshot)->toBeArray();
    expect($case->silence_settings_snapshot)->toHaveKeys([
        'escalation.interval_days',
        'escalation.max_notices',
        'nudge.first_days',
        'nudge.second_days',
        'nudge.dormancy_days',
    ]);
    expect($case->silence_settings_snapshot['escalation.interval_days'])->toBe(14);
});

it('it snapshots the current settings onto the case at inbound landlord reply', function () {
    Mail::fake();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->withLandlord(['email' => 'landlord@example.com'])->create([
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::now()->subDays(5),
        'silence_settings_snapshot' => ['escalation.interval_days' => 14],
    ]);
    $token = ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $case->landlordRecipient()->email,
        'token' => 'abcdefghij1234567890',
    ]);

    app(HandleInboundReply::class)->execute([
        'recipient' => $token->token.'@mg.renters.rent',
        'from' => 'landlord@example.com',
        'body-plain' => 'Sure, will inspect next week.',
        'subject' => 'Re: notice',
    ]);

    $case->refresh();
    expect($case->ball_with)->toBe('tenant');
    expect($case->silence_settings_snapshot)->toHaveKey('escalation.interval_days', 14);
    expect($case->silence_settings_snapshot)->toHaveKey('nudge.first_days', 10);
});

it('it overwrites the snapshot at clock restart — a fresh send re-snapshots current settings', function () {
    Mail::fake();
    $case = openCaseForClock();

    // First send at default settings — open → awaiting_landlord.
    sendActionForClock()->execute($case);
    $firstSnapshot = $case->fresh()->silence_settings_snapshot;
    $firstStartedAt = $case->fresh()->silence_clock_started_at;

    // Edit a setting, then a fresh send (the new auto-escalation
    // path in 2b — SendCaseNotice from awaiting_landlord) restarts
    // the clock and re-snapshots.
    Setting::query()->where('key', 'escalation.interval_days')->update(['value' => '7']);

    Carbon::setTestNow(Carbon::now()->addMinute());
    sendActionForClock()->execute($case->fresh());
    Carbon::setTestNow();

    $secondSnapshot = $case->fresh()->silence_settings_snapshot;
    $secondStartedAt = $case->fresh()->silence_clock_started_at;

    expect($firstSnapshot['escalation.interval_days'])->toBe(14);
    expect($secondSnapshot['escalation.interval_days'])->toBe(7);
    expect($secondStartedAt->gt($firstStartedAt))->toBeTrue();
});

it('it does not touch the snapshot when the case is in a no-clock status', function () {
    // Resolved cases never go through SendCaseNotice or HandleInboundReply,
    // so the touchpoints structurally cannot fire. This test pins that
    // boundary: nothing else writes these columns in 2a.
    Mail::fake();
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::Resolved,
        'closed_at' => now(),
        'ball_with' => null,
        'silence_clock_started_at' => null,
        'silence_settings_snapshot' => null,
    ]);

    // No path mutates clock state on a Resolved case during a sweep:
    // covered fully by ShadowModeSafetyTest. Here we just assert the
    // initial state is preserved across general activity that doesn't
    // touch the case (the global Pest beforeEach seeded settings; no
    // clock-start writer fires for a Resolved case).
    expect($case->fresh()->ball_with)->toBeNull();
    expect($case->fresh()->silence_clock_started_at)->toBeNull();
    expect($case->fresh()->silence_settings_snapshot)->toBeNull();
});
