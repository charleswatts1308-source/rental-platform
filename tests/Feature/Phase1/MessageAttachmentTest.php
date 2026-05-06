<?php

use App\Enums\MessageDirection;
use App\Enums\ScanStatus;
use App\Models\CaseMessage;
use App\Models\MessageAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a valid attachment via factory', function () {
    $attachment = MessageAttachment::factory()->create();

    expect($attachment->id)->toBeInt();
    expect($attachment->disk)->toBe('private');
    expect($attachment->mime_type)->toBe('image/jpeg');
    expect($attachment->direction)->toBe(MessageDirection::Outbound);
    expect($attachment->scan_status)->toBe(ScanStatus::Skipped);
});

it('casts direction and scan_status to enums', function () {
    $attachment = MessageAttachment::factory()->create([
        'direction' => 'inbound',
        'scan_status' => 'clean',
    ]);

    expect($attachment->direction)->toBe(MessageDirection::Inbound);
    expect($attachment->scan_status)->toBe(ScanStatus::Clean);
});

it('casts size_bytes to integer', function () {
    $attachment = MessageAttachment::factory()->create(['size_bytes' => 12345]);

    expect($attachment->size_bytes)->toBe(12345)->toBeInt();
});

it('produces an inbound attachment via the inbound state', function () {
    $attachment = MessageAttachment::factory()->inbound()->create();

    expect($attachment->direction)->toBe(MessageDirection::Inbound);
});

it('belongs to a case message', function () {
    $message = CaseMessage::factory()->create();
    $attachment = MessageAttachment::factory()->create(['case_message_id' => $message->id]);

    expect($attachment->caseMessage)->toBeInstanceOf(CaseMessage::class);
    expect($attachment->caseMessage->id)->toBe($message->id);
});

it('exposes an attachments hasMany relationship on CaseMessage', function () {
    $message = CaseMessage::factory()->create();
    MessageAttachment::factory()->count(3)->create(['case_message_id' => $message->id]);

    expect($message->attachments)->toHaveCount(3);
    expect($message->attachments->first())->toBeInstanceOf(MessageAttachment::class);
});

it('cascades deletes when the parent case message is deleted', function () {
    $message = CaseMessage::factory()->create();
    MessageAttachment::factory()->count(3)->create(['case_message_id' => $message->id]);

    expect(MessageAttachment::count())->toBe(3);

    $message->delete();

    expect(MessageAttachment::count())->toBe(0);
});
