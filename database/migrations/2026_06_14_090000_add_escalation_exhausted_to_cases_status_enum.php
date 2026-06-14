<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D14 (Phase 4) — adds `escalation_exhausted` to the cases.status ENUM.
 *
 * The terminal state a never-engaged landlord reaches by ignoring the full
 * escalation ladder. Promotes the long-shadowed transition_exhausted_intent
 * to a real transition (see SilenceSweep::executeExhaustionTransition).
 *
 * Mirrors the drop_tenant_action_required migration's MariaDB MODIFY /
 * SQLite no-op split: production runs on MariaDB and gets the real ENUM
 * widening; SQLite (tests) enforces the value set at the PHP/CaseStatus
 * layer, so the ENUM there is a dormant superset.
 *
 * NEW_VALUES appends `escalation_exhausted` to the post-TAR-drop set. This
 * is purely additive — no existing row can be truncated — so it is safe to
 * run before any case reaches the new state.
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
        'escalation_exhausted',
    ];

    private const OLD_VALUES = [
        'open',
        'awaiting_landlord',
        'awaiting_tenant_review',
        'on_hold',
        'resolved',
        'abandoned',
        'dormant',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
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
