<?php

namespace Database\Factories;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Models\LandlordContact;
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
            'landlord_contact_id' => LandlordContact::factory(),
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
     * The new contact MIRRORS the legacy landlord_contacts row this
     * factory still creates, so a fixture's landlord email and name are
     * the same whichever side reads them. That is deliberate: every
     * existing test that asserts on a recipient or a salutation keeps
     * asserting the same value, and none of them had to be weakened to
     * survive the move.
     *
     * A property that already has a current contact keeps it — Model A
     * allows exactly one, and several cases on one property is the normal
     * shape, not a conflict.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (RepairCase $case) {
            $case->loadMissing(['property', 'landlordContact']);

            if (! $case->property) {
                return;
            }

            $contact = $case->property->currentLandlordContact
                ?? $case->property->setLandlordContact([
                    'email' => $case->landlordContact?->email ?? $this->faker->unique()->safeEmail(),
                    'name' => $case->landlordContact?->name,
                    'role' => $case->landlordContact?->role,
                    'organisation_name' => $case->landlordContact?->organisation_name,
                ], $case->opened_at ?? now(), $case->tenant_user_id);

            $case->forceFill(['property_landlord_contact_id' => $contact->id])->saveQuietly();

            // The property instance resolved currentLandlordContact to
            // null a moment ago, before the contact existed, and Eloquent
            // caches that. Drop both relations so the caller's very first
            // read goes back to the database rather than to a stale null.
            $case->property->unsetRelation('currentLandlordContact');
            $case->unsetRelation('property')->unsetRelation('landlordContact');
        });
    }
}
