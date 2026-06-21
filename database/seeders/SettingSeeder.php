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
            // D15 note: for an engaged-then-quiet (held) case the
            // authorise-nudge ladder only STARTS once the escalation clock
            // expires (escalation.interval_days). So nudge.first_days is
            // INERT for held cases while interval_days (14) > first_days (10)
            // — the first authorise-nudge fires at the 14d gate, not at 10d.
            // If a pilot-pacing pass ever raises nudge.first_days ABOVE
            // escalation.interval_days, it becomes live for held cases (the
            // first authorise-nudge would then wait for first_days). Tenant-
            // side nudges always read nudge.first_days directly — unaffected.
            'nudge.first_days' => '10',
            'nudge.second_days' => '20',
            'nudge.dormancy_days' => '30',
            'dormancy.revival_days' => '90',
            'hold.max_days' => '60',
            // B2 (D16) — "Applies to In-flight cases". Ships OFF ('0'): an
            // interval change reaches NEW cases only; in-flight cases keep the
            // snapshot frozen at their clock start. When '1', SilenceClock
            // evaluates against current settings so changes reach in-flight
            // cases at the next sweep. Read live; never snapshotted.
            'escalation.apply_inflight' => '0',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
