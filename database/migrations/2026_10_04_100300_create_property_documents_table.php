<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lease agreements and anything else that belongs to a PROPERTY rather
 * than to a case. See docs/cc-brief-property-details.md.
 *
 * THE INVARIANT, AND THE REASON THIS TABLE IS NOT CALLED "attachments":
 *
 *   A DOCUMENT HERE NEVER LEAVES THE PLATFORM. It is never attached to
 *   a case, never sent to a landlord, never carried on a letter, never
 *   parsed by the application. It is visible to the tenant who uploaded
 *   it and to admin, and to nobody else.
 *
 * It is held so that a HUMAN can read it to identify the landlord's
 * formal SERVICE ADDRESS if that is ever needed — a tenancy agreement
 * normally states it. That is a person consulting a reference, not an
 * input the system consumes.
 *
 * A generic `attachments` table would not say any of that, and in six
 * months hanging one off a letter looks like a one-line change. The
 * name is doing work. `file_attachments` (recreated May 2026, wired to
 * nothing, no owner column at all) is deliberately NOT reused here.
 *
 * #18: uploaded_at is dateTime, not timestamp. It records when an act
 * happened and must not move when the row is later touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->foreignId('uploaded_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // 'lease' today. Room for a second kind without a second
            // table — but anything added here inherits the invariant
            // above, which is the point of keeping them together.
            $table->string('kind', 32)->default('lease');

            // 'local' — the PRIVATE disk, the same one case photographs
            // use. Never 'public': a lease carries the tenant's name,
            // the rent, and often third parties, and a public disk hands
            // it to anyone who can guess a filename.
            $table->string('disk', 32);
            $table->string('path', 500);

            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            // "page 1, page 2…". Nullable because a single-file upload
            // has no page to speak of. Ordering lives here so a tenant
            // can replace one page without disturbing the others — the
            // #72 shape, where a replacement wiped what was kept.
            $table->unsignedSmallInteger('page_number')->nullable();

            $table->string('scan_status', 32)->default('skipped');

            $table->dateTime('uploaded_at');

            $table->timestamps();

            $table->index(['property_id', 'kind', 'page_number'], 'property_documents_listing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_documents');
    }
};
