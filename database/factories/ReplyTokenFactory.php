<?php

namespace Database\Factories;

use App\Models\RepairCase;
use App\Models\ReplyToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReplyToken>
 */
class ReplyTokenFactory extends Factory
{
    protected $model = ReplyToken::class;

    public function definition(): array
    {
        return [
            'case_id' => RepairCase::factory(),
            'token' => Str::random(20),
            'bound_email' => $this->faker->unique()->safeEmail(),
            'issued_at' => now(),
            'expires_at' => null,
            'superseded_at' => null,
            'use_count' => 0,
            'last_used_at' => null,
        ];
    }

    public function superseded(): static
    {
        return $this->state(fn () => [
            'superseded_at' => now()->subDays(1),
            'expires_at' => now()->addDays(89),
        ]);
    }
}
