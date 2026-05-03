<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'address_line1' => $this->faker->buildingNumber().' '.$this->faker->streetName(),
            'address_line2' => null,
            'city' => $this->faker->city(),
            'postcode' => $this->ukPostcode(),
            'registered_by_user_id' => User::factory(),
        ];
    }

    private function ukPostcode(): string
    {
        return strtoupper(
            $this->faker->bothify('??#').' '.$this->faker->bothify('#??')
        );
    }
}
