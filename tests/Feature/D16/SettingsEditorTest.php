<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\Setting;
use App\Models\SettingChangeHist;
use App\Models\User;
use App\Services\Silence\IntendedAction;
use App\Services\Silence\SilenceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function settingsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

// ---- gate -------------------------------------------------------------

it('forbids a non-admin on the settings routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/settings')->assertForbidden();
    $this->actingAs($user)->put('/admin/settings', [])->assertForbidden();
});

it('shows the settings editor for an admin', function () {
    $this->actingAs(settingsAdmin())
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('escalation.interval_days')
        ->assertSee('Applies to In-flight cases');
});

// ---- B1 range-reject --------------------------------------------------

it('rejects an interval that would stall the sweep (B1)', function () {
    $this->actingAs(settingsAdmin())
        ->put('/admin/settings', validSettingsPayload(['escalation_interval_days' => 0]))
        ->assertSessionHasErrors('escalation_interval_days');

    expect(Setting::get('escalation.interval_days'))->toBe('14');
    expect(SettingChangeHist::count())->toBe(0);
});

it('rejects max_notices below 1 (B1)', function () {
    $this->actingAs(settingsAdmin())
        ->put('/admin/settings', validSettingsPayload(['escalation_max_notices' => 0]))
        ->assertSessionHasErrors('escalation_max_notices');
});

it('rejects a non-integer interval (B1)', function () {
    $this->actingAs(settingsAdmin())
        ->put('/admin/settings', validSettingsPayload(['escalation_interval_days' => 'soon']))
        ->assertSessionHasErrors('escalation_interval_days');
});

// ---- B3 audit log -----------------------------------------------------

it('writes a settings_change_hist row per changed value (B3)', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->put('/admin/settings', validSettingsPayload(['escalation_interval_days' => 7]))
        ->assertRedirect(route('admin.settings.index'));

    expect(Setting::get('escalation.interval_days'))->toBe('7');

    $row = SettingChangeHist::sole();
    expect($row->setting_key)->toBe('escalation.interval_days');
    expect($row->old_value)->toBe('14');
    expect($row->new_value)->toBe('7');
    expect($row->edited_by_user_id)->toBe($admin->id);
});

it('writes no audit row for an unchanged save (B3)', function () {
    $this->actingAs(settingsAdmin())
        ->put('/admin/settings', validSettingsPayload())
        ->assertRedirect(route('admin.settings.index'));

    expect(SettingChangeHist::count())->toBe(0);
});

// ---- B2 flag: ships Off, both positions -------------------------------

it('ships the in-flight flag Off by default', function () {
    expect(Setting::get('escalation.apply_inflight'))->toBe('0');
    expect(SilenceClock::applyInflight())->toBeFalse();
});

it('with the flag Off, a live interval change does NOT move an in-flight case (B2)', function () {
    $case = inflightLandlordCase(snapshotInterval: 14);
    Setting::updateOrCreate(['key' => 'escalation.interval_days'], ['value' => '7']);
    Setting::updateOrCreate(['key' => 'escalation.apply_inflight'], ['value' => '0']);

    // 9 days in: snapshot interval (14) still governs -> clock not expired.
    $now = $case->silence_clock_started_at->copy()->addDays(9);
    $verdict = app(SilenceClock::class)->evaluate($case, $now);

    expect($verdict->intendedAction)->toBe(IntendedAction::NoAction);
});

it('with the flag On, a live interval change DOES reach an in-flight case (B2)', function () {
    $case = inflightLandlordCase(snapshotInterval: 14);
    Setting::updateOrCreate(['key' => 'escalation.interval_days'], ['value' => '7']);
    Setting::updateOrCreate(['key' => 'escalation.apply_inflight'], ['value' => '1']);

    // Same 9 days in: live interval (7) now governs -> clock expired, escalate.
    $now = $case->silence_clock_started_at->copy()->addDays(9);
    $verdict = app(SilenceClock::class)->evaluate($case, $now);

    expect($verdict->intendedAction)->toBe(IntendedAction::SendEscalation);
});

it('saving the flag On is audit-logged and read live (B2/B3)', function () {
    $this->actingAs(settingsAdmin())
        ->put('/admin/settings', validSettingsPayload(['escalation_apply_inflight' => '1']));

    expect(SilenceClock::applyInflight())->toBeTrue();
    expect(SettingChangeHist::where('setting_key', 'escalation.apply_inflight')->count())->toBe(1);
});

/**
 * A full valid settings payload (current defaults), with optional overrides
 * keyed by the underscore-safe field names the form uses.
 */
function validSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'escalation_interval_days' => 14,
        'escalation_max_notices' => 4,
        'nudge_first_days' => 10,
        'nudge_second_days' => 20,
        'nudge_dormancy_days' => 30,
        'dormancy_revival_days' => 90,
        'hold_max_days' => 60,
        'escalation_apply_inflight' => '0',
        'attachments_first_notice_max' => 1,
    ], $overrides);
}

/**
 * A landlord-ball case mid-clock: one outbound escalation sent (counter=1),
 * never engaged, with a frozen snapshot at the given interval.
 */
function inflightLandlordCase(int $snapshotInterval): RepairCase
{
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'landlord_engaged' => false,
        'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
        'silence_settings_snapshot' => [
            'escalation.interval_days' => $snapshotInterval,
            'escalation.max_notices' => 4,
            'nudge.first_days' => 10,
            'nudge.second_days' => 20,
            'nudge.dormancy_days' => 30,
        ],
    ]);

    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => 1,
    ]);

    return $case->fresh();
}
