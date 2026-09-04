<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D17 (#25) — adds `contact_failed` to the cases.status ENUM.
 *
 * The terminal state a case reaches when a letter to the landlord
 * PERMANENTLY fails to deliver. Silence escalates; a bounce is the
 * opposite and must stop the ladder (D17.2). Entry and exit rules are
 * D17.8 and live in RepairCase::TRANSITIONS.
 *
 * Mirrors the add_escalation_exhausted migration's MariaDB MODIFY /
 * SQLite no-op split: production runs on MariaDB and gets the real ENUM
 * widening; SQLite (tests) enforces the value set at the PHP/CaseStatus
 * layer, so the ENUM there is a dormant superset.
 *
 * NEW_VALUES appends `contact_failed` to the post-escalation_exhausted
 * set. Purely additive — no existing row can be truncated — so it is
 * safe to run before any case reaches the new state.
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
        'contact_failed',
    ];

    private const OLD_VALUES = [
        'open',
        'awaiting_landlord',
        'awaiting_tenant_review',
        'on_hold',
        'resolved',
        'abandoned',
        'dormant',
        'escalation_exhausted',
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
