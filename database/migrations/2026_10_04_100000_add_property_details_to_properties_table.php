<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property type + "do you have a lease agreement?". See
 * docs/cc-brief-property-details.md.
 *
 * STEP 1 OF 3, and the split is the point. Both columns end up NOT
 * NULL, but gafol and prod already hold properties, so adding them as
 * NOT NULL in one step would force a database-level default — and a
 * default is exactly what must not exist here. A default silently
 * answers the question for every row inserted by any future code path
 * that forgets to ask, and the answer would be indistinguishable from
 * one a tenant gave.
 *
 * So: add nullable (here), backfill explicitly (next), tighten to
 * NOT NULL (after that). Every row that ends up non-null got there by
 * a deliberate act that is recorded in a migration.
 *
 * Plain strings rather than enum() columns: the valid set lives in
 * App\Enums\PropertyType and App\Enums\LeaseAgreementAnswer, and
 * widening a PHP enum should not require an ALTER on two live boxes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_type', 32)->nullable()->after('postcode');
            $table->string('has_lease_agreement', 16)->nullable()->after('property_type');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['property_type', 'has_lease_agreement']);
        });
    }
};
