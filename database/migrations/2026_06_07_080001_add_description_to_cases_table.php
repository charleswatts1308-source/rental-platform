<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D9 — adds cases.description: the tenant's original framing of the
 * issue. Set at case creation, immutable thereafter. Every system-
 * rendered outbound email carries the standing header block: property
 * address + case reference + this description.
 *
 * Hard-cut NOT NULL per the Phase 3 D0 ruling — pre-flip, gafol is
 * empty after dev:reset, and dotrent's case count was verified
 * separately before this lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->text('description')->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
