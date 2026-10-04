<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 3 OF 3 — see the step-1 migration for why this is separate.
 *
 * Both columns become NOT NULL now that every existing row has been
 * given `not_specified` explicitly.
 *
 * NO DATABASE DEFAULT IS SET, deliberately. NOT NULL with no default
 * means a future code path that forgets to supply a value FAILS LOUDLY
 * on insert, rather than quietly recording an answer nobody gave. That
 * noise is the feature: this field exists to be counted, and a silent
 * default is how a statistic becomes a fiction.
 *
 * #18 WATCH: `change()` rewrites the column definition on MariaDB, so
 * the manual check before merge must confirm these two came back as
 * plain varchars with no default and no ON UPDATE, and that the
 * timestamps either side of them are untouched. SQLite in the test
 * suite cannot show any of that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_type', 32)->nullable(false)->change();
            $table->string('has_lease_agreement', 16)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_type', 32)->nullable()->change();
            $table->string('has_lease_agreement', 16)->nullable()->change();
        });
    }
};
