<?php

use App\Models\CaseEvent;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates a valid case event via factory', function () {
    $event = CaseEvent::factory()->create();

    expect($event->id)->toBeInt();
    expect($event->event_type)->toBe('case_opened');
    expect($event->actor_label)->toBe('system');
    expect($event->meta)->toBeNull();
});

it('does not have an updated_at column', function () {
    expect(Schema::hasColumn('case_events', 'updated_at'))->toBeFalse();
    expect(Schema::hasColumn('case_events', 'created_at'))->toBeTrue();
});

it('disables updated_at on the model', function () {
    expect(CaseEvent::UPDATED_AT)->toBeNull();
});

it('casts occurred_at to a Carbon instance', function () {
    $event = CaseEvent::factory()->create(['occurred_at' => now()]);

    expect($event->occurred_at)->toBeInstanceOf(Carbon::class);
});

it('casts meta to an array', function () {
    $event = CaseEvent::factory()->create([
        'meta' => ['reason' => 'unexpected_from_address', 'attempts' => 3],
    ]);

    expect($event->meta)->toBeArray();
    expect($event->meta['reason'])->toBe('unexpected_from_address');
    expect($event->meta['attempts'])->toBe(3);
});

it('belongs to a repair case', function () {
    $case = RepairCase::factory()->create();
    $event = CaseEvent::factory()->create(['case_id' => $case->id]);

    expect($event->case)->toBeInstanceOf(RepairCase::class);
    expect($event->case->id)->toBe($case->id);
});

it('belongs to an actor user when present', function () {
    $user = User::factory()->create();
    $event = CaseEvent::factory()->create(['actor_user_id' => $user->id]);

    expect($event->actor)->toBeInstanceOf(User::class);
    expect($event->actor->id)->toBe($user->id);
});

it('allows a null actor for system-originated events', function () {
    $event = CaseEvent::factory()->create(['actor_user_id' => null]);

    expect($event->actor)->toBeNull();
});

it('exposes an events hasMany relationship on RepairCase', function () {
    $case = RepairCase::factory()->create();
    $eventCountBefore = $case->events()->count();
    CaseEvent::factory()->count(3)->create(['case_id' => $case->id]);

    expect($case->fresh()->events)->toHaveCount($eventCountBefore + 3);
    expect($case->events->first())->toBeInstanceOf(CaseEvent::class);
});

it('cascades deletes when the parent case is deleted', function () {
    $case = RepairCase::factory()->create();
    CaseEvent::factory()->count(3)->create(['case_id' => $case->id]);

    expect($case->events()->count())->toBeGreaterThanOrEqual(3);

    $case->delete();

    expect(CaseEvent::count())->toBe(0);
});

it('sets actor_user_id to null when the actor user is deleted', function () {
    $user = User::factory()->create();
    $event = CaseEvent::factory()->create(['actor_user_id' => $user->id]);

    expect($event->fresh()->actor_user_id)->toBe($user->id);

    $user->delete();

    expect($event->fresh()->actor_user_id)->toBeNull();
});
