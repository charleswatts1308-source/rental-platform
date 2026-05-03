<?php

namespace Database\Factories;

use App\Models\CaseEvent;
use App\Models\RepairCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseEvent>
 */
class CaseEventFactory extends Factory
{
    protected $model = CaseEvent::class;

    public function definition(): array
    {
        return [
            'case_id' => RepairCase::factory(),
            'event_type' => 'case_opened',
            'actor_user_id' => null,
            'actor_label' => 'system',
            'occurred_at' => now(),
            'meta' => null,
        ];
    }
}
