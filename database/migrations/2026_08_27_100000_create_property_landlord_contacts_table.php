<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Model A — a property has exactly ONE landlord contact at a time,
 * versioned over time. See docs/cc-report-property-landlord-contacts-d0.md.
 *
 * Two schema choices worth stating here rather than in the doc alone:
 *
 * 1. `is_current` + UNIQUE(property_id, is_current) is how "one current
 *    contact per property" becomes a database guarantee. MariaDB has no
 *    partial indexes, and NULLs do not collide in a unique index on
 *    either MariaDB or SQLite, so a nullable flag gives the same
 *    invariant on both engines. Without it a double-submitted edit forks
 *    a property into two live contacts and routing becomes a coin toss.
 *
 * 2. effective_from / superseded_at are dateTime, NOT timestamp — the #18
 *    implicit ON UPDATE CURRENT_TIMESTAMP trap. These record when a
 *    decision was taken and must never move on a later row update.
 *
 * There is deliberately NO unique index on email: the email is a contact
 * channel whose natural grain is the property, and it may legitimately
 * repeat across properties and across tenants. That absence is what
 * closes snag #49(a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_landlord_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();

            $table->string('email');
            $table->string('name')->nullable();
            $table->enum('role', ['landlord', 'agent'])->default('landlord');
            $table->string('organisation_name')->nullable();

            // Landlord's own postal address. Store-and-display only: never
            // reaches buildLetterVars or any template.
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postcode', 20)->nullable();

            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->dateTime('effective_from');
            $table->dateTime('superseded_at')->nullable();

            $table->foreignId('superseded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedTinyInteger('is_current')->nullable();

            // Provenance. A 'backfilled' row's effective_from is a
            // reconstruction from case records, not an observed change,
            // and the history view says so.
            $table->enum('source', ['entered', 'backfilled'])->default('entered');

            $table->timestamps();

            $table->unique(['property_id', 'is_current']);
            $table->index(['property_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_landlord_contacts');
    }
};
