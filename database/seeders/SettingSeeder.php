<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the v1 setting defaults per silence-model design D4.
 *
 * IMPORTANT — Phase 1 scope: these rows are SEEDED ONLY. No runtime
 * code reads them yet. Consumers arrive in Phase 2a (clock fields and
 * the silence-detecting scheduler). Adding a reader in this phase
 * would constitute behaviour change.
 *
 * In-flight semantics (D4 guardrail #1): once readers exist, a change
 * to a setting must apply only to clocks started after the change.
 * Deadlines are computed from the value in force at clock start —
 * never re-evaluated. That guardrail lives at the consumer, not here.
 *
 * Idempotent: existing keys are updated; new keys are inserted.
 * Editing path for production tuning is phpMyAdmin (the admin CRUD
 * is Phase 5).
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'escalation.interval_days' => '14',
            'escalation.max_notices' => '4',
            'nudge.first_days' => '10',
            'nudge.second_days' => '20',
            'nudge.dormancy_days' => '30',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
