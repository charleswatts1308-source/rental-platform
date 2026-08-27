<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The create path stops writing landlord_contact_id in this commit —
 * nothing reads it any more, so continuing to mint global contact rows
 * would only be manufacturing junk for the final commit to drop.
 *
 * Uses the schema builder rather than raw ALTER ... MODIFY: the test
 * suite runs SQLite, which has no such statement, and a MariaDB-only
 * migration takes the whole suite down with it. (It did.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedBigInteger('landlord_contact_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // A row created after the column stopped being written has no
        // legacy contact and no honest way to acquire one. Refuse rather
        // than invent a value.
        $orphaned = DB::table('cases')->whereNull('landlord_contact_id')->count();

        if ($orphaned > 0) {
            throw new RuntimeException(
                "Cannot restore NOT NULL: {$orphaned} cases have no landlord_contact_id. "
                .'These were created after the column stopped being written.'
            );
        }

        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedBigInteger('landlord_contact_id')->nullable(false)->change();
        });
    }
};
