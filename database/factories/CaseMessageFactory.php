<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseMessage>
 */
class CaseMessageFactory extends Factory
{
    protected $model = CaseMessage::class;

    public function definition(): array
    {
        return [
            'case_id' => RepairCase::factory(),
            'direction' => MessageDirection::Outbound,
            'sender_role' => SenderRole::System,
            'stage_at_send' => 1,
            'template_key' => 'stage_1_initial_notice',
            'subject' => $this->faker->sentence(),
            'body_raw' => $this->faker->paragraph(),
            'body_sanitised' => null,
            'tenant_statement' => null,
            'from_address_raw' => null,
            'to_address_raw' => $this->faker->safeEmail(),
            'spf_pass' => null,
            'dkim_pass' => null,
            'mailgun_message_id' => null,
            'quarantine_reason' => null,
            'sent_at' => now(),
            'received_at' => null,
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => MessageDirection::Inbound,
            'sender_role' => SenderRole::Landlord,
            'stage_at_send' => null,
            'template_key' => null,
            'body_sanitised' => $this->faker->paragraph(),
            'from_address_raw' => $this->faker->safeEmail(),
            'spf_pass' => true,
            'dkim_pass' => true,
            'sent_at' => null,
            'received_at' => now(),
        ]);
    }
}
