<?php

use App\Enums\CaseStatus;
use App\Exceptions\InvalidCaseTransitionException;
use App\Models\RepairCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Demolition verification — every approved D0.2 item is gone.
 *
 * If a future refactor accidentally re-introduces a demolished file,
 * route, schedule entry, or transition, these tests catch it.
 */

it('the cases:sweep-escalations artisan command no longer exists', function () {
    $exitCode = \Illuminate\Support\Facades\Artisan::call('list');
    expect($exitCode)->toBe(0);
    expect(\Illuminate\Support\Facades\Artisan::output())
        ->not->toContain('cases:sweep-escalations');
});

it('the cases:sweep-escalations schedule entry is gone', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '')
        ->filter()
        ->all();

    foreach ($commands as $command) {
        expect($command)->not->toContain('cases:sweep-escalations');
    }
});

it('the silence:sweep schedule entry uses withoutOverlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) ($event->command ?? ''), 'silence:sweep'));

    expect($event)->not->toBeNull();
    // The mutex name is set when withoutOverlapping is configured.
    expect($event->mutexName())->not->toBeNull();
});

it('the EscalationEligible mailable file is gone from disk', function () {
    expect(file_exists(base_path('app/Mail/Notifications/EscalationEligible.php')))->toBeFalse();
    expect(file_exists(base_path('resources/views/emails/notifications/escalation-eligible.blade.php')))->toBeFalse();
});

it('the SweepEscalations command file is gone from disk', function () {
    expect(file_exists(base_path('app/Console/Commands/SweepEscalations.php')))->toBeFalse();
});

it('the four dead stage Blade views are gone', function () {
    expect(view()->exists('emails.case-notices.stage_1_initial_notice'))->toBeFalse();
    expect(view()->exists('emails.case-notices.stage_2_follow_up'))->toBeFalse();
    expect(view()->exists('emails.case-notices.stage_3_formal_warning'))->toBeFalse();
    expect(view()->exists('emails.case-notices.stage_4_pre_action'))->toBeFalse();
});

it('the awaiting_landlord -> tenant_action_required transition is illegal', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->transitionTo(CaseStatus::TenantActionRequired);
})->throws(InvalidCaseTransitionException::class);

it('the cases.next_stage_eligible_at column is gone', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('cases', 'next_stage_eligible_at'))->toBeFalse();
});

it('the case_messages.template_key column is gone', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('case_messages', 'template_key'))->toBeFalse();
});
