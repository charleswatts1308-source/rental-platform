<?php

namespace Database\Factories;

use App\Models\RepairCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RepairCategory>
 */
class RepairCategoryFactory extends Factory
{
    protected $model = RepairCategory::class;

    public function definition(): array
    {
        $label = $this->faker->unique()->words(2, true);

        return [
            'key' => Str::slug($label, '_').'_'.Str::random(4),
            'label' => ucfirst($label),
            'description' => null,
            'sort_order' => $this->faker->numberBetween(10, 1000),
            'active' => true,
            'requires_description' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
