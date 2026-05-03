<?php

use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates a valid outbound case message via factory', function () {
    $message = CaseMessage::factory()->create();

    expect($message->id)->toBeInt();
    expect($message->direction)->toBe(MessageDirection::Outbound);
    expect($message->sender_role)->toBe(SenderRole::System);
    expect($message->body_raw)->toBeString();
});

it('creates an inbound case message via the inbound state', function () {
    $message = CaseMessage::factory()->inbound()->create();

    expect($message->direction)->toBe(MessageDirection::Inbound);
    expect($message->sender_role)->toBe(SenderRole::Landlord);
    expect($message->received_at)->toBeInstanceOf(Carbon::class);
    expect($message->sent_at)->toBeNull();
    expect($message->spf_pass)->toBeTrue();
});

it('casts direction and sender_role to enums', function () {
    $message = CaseMessage::factory()->create([
        'direction' => 'inbound',
        'sender_role' => 'tenant',
    ]);

    expect($message->direction)->toBe(MessageDirection::Inbound);
    expect($message->sender_role)->toBe(SenderRole::Tenant);
});

it('casts spf_pass and dkim_pass to booleans', function () {
    $message = CaseMessage::factory()->create([
        'spf_pass' => 1,
        'dkim_pass' => 0,
    ]);

    expect($message->spf_pass)->toBeTrue();
    expect($message->dkim_pass)->toBeFalse();
});

it('casts stage_at_send to integer', function () {
    $message = CaseMessage::factory()->create(['stage_at_send' => 2]);

    expect($message->stage_at_send)->toBe(2)->toBeInt();
});

it('casts sent_at and received_at to Carbon instances', function () {
    $message = CaseMessage::factory()->create([
        'sent_at' => now(),
        'received_at' => now(),
    ]);

    expect($message->sent_at)->toBeInstanceOf(Carbon::class);
    expect($message->received_at)->toBeInstanceOf(Carbon::class);
});

it('belongs to a repair case', function () {
    $case = RepairCase::factory()->create();
    $message = CaseMessage::factory()->create(['case_id' => $case->id]);

    expect($message->case)->toBeInstanceOf(RepairCase::class);
    expect($message->case->id)->toBe($case->id);
});

it('exposes a messages hasMany relationship on RepairCase', function () {
    $case = RepairCase::factory()->create();
    CaseMessage::factory()->count(3)->create(['case_id' => $case->id]);

    expect($case->messages)->toHaveCount(3);
    expect($case->messages->first())->toBeInstanceOf(CaseMessage::class);
});

it('cascades deletes when the parent case is deleted', function () {
    $case = RepairCase::factory()->create();
    CaseMessage::factory()->count(3)->create(['case_id' => $case->id]);

    expect(CaseMessage::count())->toBe(3);

    $case->delete();

    expect(CaseMessage::count())->toBe(0);
});
