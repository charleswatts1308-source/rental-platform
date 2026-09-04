<?php

namespace Database\Factories;

use App\Enums\ContactSource;
use App\Enums\LandlordContactRole;
use App\Models\Property;
use App\Models\PropertyLandlordContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyLandlordContact>
 */
class PropertyLandlordContactFactory extends Factory
{
    protected $model = PropertyLandlordContact::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            // Unique by default only so unrelated fixtures do not collide
            // by accident. There is NO unique index on this column — two
            // properties sharing an email is legitimate, and a test that
            // needs that can ask for it explicitly.
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'role' => LandlordContactRole::Landlord,
            'organisation_name' => null,
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'postcode' => null,
            'created_by_user_id' => User::factory(),
            'effective_from' => now(),
            'superseded_at' => null,
            'superseded_by_user_id' => null,
            'is_current' => 1,
            'source' => ContactSource::Entered,
        ];
    }

    public function agent(): static
    {
        return $this->state(fn () => [
            'role' => LandlordContactRole::Agent,
            'organisation_name' => $this->faker->company(),
        ]);
    }

    public function withPostalAddress(): static
    {
        return $this->state(fn () => [
            'address_line1' => $this->faker->streetAddress(),
            'address_line2' => null,
            'city' => $this->faker->city(),
            'postcode' => 'M1 1AA',
        ]);
    }

    /** A retired version. Releases the (property_id, is_current) slot. */
    public function superseded(): static
    {
        return $this->state(fn () => [
            'superseded_at' => now(),
            'is_current' => null,
        ]);
    }

    public function backfilled(): static
    {
        return $this->state(fn () => ['source' => ContactSource::Backfilled]);
    }
}
