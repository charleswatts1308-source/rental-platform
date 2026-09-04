<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the global email-keyed landlord_contacts entity.
 *
 * Nothing has read this table since the routing flip, and nothing has
 * written it since the create path started inheriting. What remains is
 * the rows themselves — every one of which was already copied onto its
 * property by the backfill, EXCEPT those with no case at all. Those had
 * no property to attach to and are discarded here. The count is reported
 * at migrate time so it lands in the deploy log; snapshot the table
 * before running this on a long-lived environment.
 *
 * Deliberately the LAST commit, and separable: it can be held to a
 * second deploy, so a schema problem here cannot force a rollback of
 * working behaviour.
 *
 * Irreversible by design. down() restores the shape but not the data —
 * the rows are gone, and inventing replacements would be worse than
 * failing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $discarded = Schema::hasTable('landlord_contacts')
            ? DB::table('landlord_contacts')->count()
            : 0;

        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['landlord_contact_id']);
            $table->dropColumn('landlord_contact_id');
        });

        Schema::dropIfExists('landlord_contacts');

        if ($discarded > 0) {
            echo sprintf("  dropped landlord_contacts: %d rows discarded\n", $discarded);
        }
    }

    public function down(): void
    {
        Schema::create('landlord_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->enum('role', ['landlord', 'agent'])->default('landlord');
            $table->string('organisation_name')->nullable();
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('landlord_contact_id')
                ->nullable()
                ->after('property_id')
                ->constrained('landlord_contacts')
                ->restrictOnDelete();
        });
    }
};
