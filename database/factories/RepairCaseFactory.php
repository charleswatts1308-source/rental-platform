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
}
