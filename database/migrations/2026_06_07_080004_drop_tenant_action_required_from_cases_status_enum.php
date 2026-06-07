<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D7 resolution (Option A): the tenant_action_required state is
 * demolished. Nothing in production code reaches it after Phase 3:
 *   - SweepEscalations (the historical driver) was demolished in 2b.
 *   - sendNext (the tenant click into AwaitingLandlord) is demolished
 *     in Phase 3 with D7 resolved (escalation is silence-only).
 *   - SweepHolds (OnHold → TAR) is absorbed into silence:sweep,
 *     which now targets AwaitingLandlord directly (ball back to
 *     landlord; landlord still owes the response).
 *   - reEngage (Dormant → TAR) is demolished — dormant cases revive
 *     by replying (D8); a separate re-engage step is dead weight.
 *
 * With no drivers in, a state nothing can reach is dead code. This
 * migration drops the enum value from the cases.status column.
 *
 * Pre-flight: no rows with status='tenant_action_required' should
 * exist by the time this migration runs (gafol is wiped on every
 * dev:reset; dotrent operator-verified empty before this lands).
 * MariaDB's ALTER on the enum will fail with "Data truncated" if
 * any row holds a value not in the new list, which is the right
 * behaviour — we want a noisy failure, not silent data loss.
 */
return new class extends Migration
{
    private const NEW_VALUES = [
        'open',
        'awaiting_landlord',
        'awaiting_tenant_review',
        'on_hold',
        'resolved',
        'abandoned',
        'dormant',
    ];

    private const OLD_VALUES = [
        'open',
        'awaiting_landlord',
        'awaiting_tenant_review',
        'tenant_action_required',
        'on_hold',
        'resolved',
        'abandoned',
        'dormant',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite has no MODIFY COLUMN; the cases.status ENUM is
            // enforced at the PHP/CaseStatus layer (the value is no
            // longer a valid enum case, so test code cannot insert it).
            // The original CHECK constraint stays as a dormant superset;
            // production runs on MariaDB and gets the actual ENUM drop.
            return;
        }

        DB::statement($this->modifyEnumSql(self::NEW_VALUES));
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement($this->modifyEnumSql(self::OLD_VALUES));
    }

    /**
     * @param  array<int, string>  $values
     */
    private function modifyEnumSql(array $values): string
    {
        $quoted = implode(',', array_map(fn (string $v): string => "'{$v}'", $values));

        return "ALTER TABLE `cases` MODIFY COLUMN `status` ENUM({$quoted}) NOT NULL DEFAULT 'open'";
    }
};
