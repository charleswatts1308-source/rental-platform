<?php

namespace Database\Factories;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Enums\LandlordContactRole;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\User;
use App\Support\CaseReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairCase>
 */
class RepairCaseFactory extends Factory
{
    protected $model = RepairCase::class;

    public function definition(): array
    {
        return [
            'url_slug' => CaseReference::generate(),
            'tenant_user_id' => User::factory(),
            'property_id' => Property::factory(),
            'category_key' => fn () => RepairCategory::factory()->create()->key,
            'severity' => CaseSeverity::Routine,
            'description' => 'Sample issue description for test fixtures.',
            'status' => CaseStatus::Open,
            'current_stage' => 1,
            'hold_until' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'dormant_at' => null,
        ];
    }

    /**
     * Give the case's property a current landlord contact, and point the
     * case's provenance FK at it.
     *
     * A property that already has a current contact keeps it — Model A
     * allows exactly one, and several cases on one property is the normal
     * shape, not a conflict. A fixture wanting a specific address sets it
     * on the property before or after; this only guarantees the case has
     * somewhere to send.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (RepairCase $case) {
            $case->loadMissing('property');

            if (! $case->property) {
                return;
            }

            $contact = $case->property->currentLandlordContact
                ?? $case->property->setLandlordContact([
                    'email' => $this->faker->unique()->safeEmail(),
                    'name' => $this->faker->name(),
                    'role' => LandlordContactRole::Landlord,
                    'organisation_name' => null,
                ], $case->opened_at ?? now(), $case->tenant_user_id);

            $case->forceFill(['property_landlord_contact_id' => $contact->id])->saveQuietly();

            // The property instance resolved currentLandlordContact to
            // null a moment ago, before the contact existed, and Eloquent
            // caches that. Drop both relations so the caller's very first
            // read goes back to the database rather than to a stale null.
            $case->property->unsetRelation('currentLandlordContact');
            $case->unsetRelation('property');
        });
    }

    /**
     * Pin the landlord details a fixture depends on.
     *
     * Replaces the old `LandlordContact::factory()->create([...])` +
     * `'landlord_contact_id' => $contact->id` pair. It OVERWRITES the
     * contact configure() just created rather than superseding it: a
     * fixture stating its landlord is not a correction, and it should not
     * leave a version history behind it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withLandlord(array $attributes): static
    {
        return $this->afterCreating(function (RepairCase $case) use ($attributes) {
            $case->loadMissing('property');
            $case->property?->currentLandlordContact?->forceFill($attributes)->save();

            $case->property?->unsetRelation('currentLandlordContact');
            $case->unsetRelation('property');
        });
    }
}
