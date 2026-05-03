<?php

namespace Database\Factories;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Models\LandlordContact;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RepairCase>
 */
class RepairCaseFactory extends Factory
{
    protected $model = RepairCase::class;

    public function definition(): array
    {
        return [
            'url_slug' => Str::random(12),
            'tenant_user_id' => User::factory(),
            'property_id' => Property::factory(),
            'landlord_contact_id' => LandlordContact::factory(),
            'category_key' => fn () => RepairCategory::factory()->create()->key,
            'severity' => CaseSeverity::Routine,
            'status' => CaseStatus::Open,
            'current_stage' => 1,
            'next_stage_eligible_at' => null,
            'hold_until' => null,
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }
}
