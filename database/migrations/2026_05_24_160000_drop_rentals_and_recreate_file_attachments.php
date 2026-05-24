<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the legacy rentals feature and recreates file_attachments as a
 * standalone table.
 *
 * The old file_attachments was tied to rentals via a rental_id foreign key.
 * Rentals is being retired, but the attachment table is still wanted — it
 * will be wired into properties/cases later. Rather than detach the FK in
 * place, we drop the old table (which removes the FK), drop rentals, then
 * recreate file_attachments without any owner column for now.
 *
 * down() drops the recreated table; rentals is not restored (deliberate
 * permanent removal — its create migrations are gone).
 */
return new class extends Migration
{
    public function up(): void
    {
        // file_attachments first: dropping it removes the rental_id FK that
        // would otherwise block dropping rentals.
        Schema::dropIfExists('file_attachments');
        Schema::dropIfExists('rentals');

        Schema::create('file_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('file_name', 255);
            $table->string('blob_url', 500);
            $table->string('content_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->timestamp('uploaded_date');

            $table->index('uploaded_date');
            $table->index('content_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_attachments');
    }
};
