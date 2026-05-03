<?php

use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('writes a case_opened event when a case is created', function () {
    $case = RepairCase::factory()->create();

    $events = $case->events()->where('event_type', 'case_opened')->get();

    expect($events)->toHaveCount(1);
});

it('records the tenant as the actor on the case_opened event', function () {
    $case = RepairCase::factory()->create();

    $event = $case->events()->where('event_type', 'case_opened')->sole();

    expect($event->actor_label)->toBe('tenant');
    expect($event->actor_user_id)->toBe($case->tenant_user_id);
});

it('uses the case opened_at timestamp on the case_opened event', function () {
    $openedAt = now()->subHour();
    $case = RepairCase::factory()->create(['opened_at' => $openedAt]);

    $event = $case->events()->where('event_type', 'case_opened')->sole();

    expect($event->occurred_at->toIso8601String())->toBe($openedAt->toIso8601String());
});
