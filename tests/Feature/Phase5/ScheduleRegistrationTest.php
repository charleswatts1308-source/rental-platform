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

// cases:sweep-escalations registration test removed per
// silence-phase-2b D0.1 (#8) — the command and schedule entry are
// demolished.

it('registers cases:sweep-holds on the scheduler', function () {
    expect(commandIsScheduled('cases:sweep-holds'))->toBeTrue();
});

it('registers cases:sweep-dormancy on the scheduler', function () {
    expect(commandIsScheduled('cases:sweep-dormancy'))->toBeTrue();
});

it('registers silence:sweep on the scheduler', function () {
    expect(commandIsScheduled('silence:sweep'))->toBeTrue();
});
