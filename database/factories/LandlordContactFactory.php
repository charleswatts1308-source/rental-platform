<?php

namespace Database\Factories;

use App\Enums\LandlordContactRole;
use App\Models\LandlordContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LandlordContact>
 */
class LandlordContactFactory extends Factory
{
    protected $model = LandlordContact::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'role' => LandlordContactRole::Landlord,
            'organisation_name' => null,
            'invited_by_user_id' => User::factory(),
            'verified_at' => null,
        ];
    }

    public function agent(): static
    {
        return $this->state(fn () => [
            'role' => LandlordContactRole::Agent,
            'organisation_name' => $this->faker->company(),
        ]);
    }
}
