<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable alongside the existing landlord_contact_id. Both columns
 * coexist until the final commit drops the old one, so every step up to
 * that point is additive and rolls back clean.
 *
 * This column is PROVENANCE — what the case opened with. It is never
 * used for routing: routing resolves the property's CURRENT contact, or
 * correcting an address would not reach the case's next letter and snag
 * #24 would stay open. See §5 of the D0 report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('property_landlord_contact_id')
                ->nullable()
                ->after('landlord_contact_id')
                ->constrained('property_landlord_contacts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['property_landlord_contact_id']);
            $table->dropColumn('property_landlord_contact_id');
        });
    }
};
