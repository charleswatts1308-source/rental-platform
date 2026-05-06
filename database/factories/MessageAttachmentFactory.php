<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\ScanStatus;
use App\Models\CaseMessage;
use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageAttachment>
 */
class MessageAttachmentFactory extends Factory
{
    protected $model = MessageAttachment::class;

    public function definition(): array
    {
        return [
            'case_message_id' => CaseMessage::factory(),
            'disk' => 'private',
            'path' => 'attachments/'.$this->faker->uuid().'.jpg',
            'original_filename' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(1024, 5_000_000),
            'direction' => MessageDirection::Outbound,
            'scan_status' => ScanStatus::Skipped,
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => MessageDirection::Inbound,
        ]);
    }
}
