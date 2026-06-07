<?php

use Illuminate\Console\Scheduling\Schedule;

function scheduledCommandStrings(): array
{
    return collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '')
        ->filter()
        ->all();
}

function commandIsScheduled(string $name): bool
{
    foreach (scheduledCommandStrings() as $command) {
        if (str_contains($command, $name)) {
            return true;
        }
    }

    return false;
}

// Phase 3 — cases:sweep-holds and cases:sweep-dormancy are demolished;
// both behaviours are absorbed into silence:sweep (tenant-side LIVE +
// hold expiry as the new ResumeFromHold verdict).

it('registers silence:sweep on the scheduler', function () {
    expect(commandIsScheduled('silence:sweep'))->toBeTrue();
});

it('does not register the demolished cases:sweep-holds command', function () {
    expect(commandIsScheduled('cases:sweep-holds'))->toBeFalse();
});

it('does not register the demolished cases:sweep-dormancy command', function () {
    expect(commandIsScheduled('cases:sweep-dormancy'))->toBeFalse();
});
