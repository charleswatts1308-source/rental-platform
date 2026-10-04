<?php

namespace Database\Factories;

use App\Models\Property;
use App\Enums\LeaseAgreementAnswer;
use App\Enums\PropertyType;
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
            // Both columns are NOT NULL with no database default, so the
            // factory has to supply them. A concrete type rather than a
            // random one: a test that cares about the value says so, and
            // a test that does not should not be reading a dice roll.
            'property_type' => PropertyType::Terraced,
            'has_lease_agreement' => LeaseAgreementAnswer::Yes,
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
