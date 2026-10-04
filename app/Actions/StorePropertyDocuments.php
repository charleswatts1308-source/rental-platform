<?php

namespace App\Actions;

use App\Enums\ScanStatus;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Store uploaded lease pages against a property.
 *
 * Shared by the property CREATE form and the property EDIT form, because
 * the upload is offered on both (ruled 4 Oct 2026: a tenant registering a
 * property usually has the lease in front of them already, since that is
 * where the landlord's email address came from).
 *
 * THE INVARIANT THIS SERVES — see App\Models\PropertyDocument for the
 * full statement. Nothing written here is ever attached to a case, sent
 * to a landlord, or carried on a letter. It is reference material a
 * human reads to identify the landlord's formal service address.
 *
 * Files land on the PRIVATE disk under a per-property folder. Names are
 * random, not the tenant's own filename: an original name can carry a
 * person's name, and a path is a thing that leaks.
 */
class StorePropertyDocuments
{
    public const DISK = 'local';

    /** Per property, across every page. Generous; a lease is not a gallery. */
    public const MAX_PAGES = 20;

    public const KIND_LEASE = 'lease';

    /**
     * @param  array<int, UploadedFile|null>  $files
     * @return int how many were actually stored
     */
    public function handle(Property $property, User $uploader, array $files): int
    {
        // Page numbers continue from whatever is already there, so adding
        // page 3 later does not renumber pages 1 and 2 — the #72 shape,
        // where a new upload quietly rewrote what the tenant had kept.
        $nextPage = (int) $property->documents()
            ->where('kind', self::KIND_LEASE)
            ->max('page_number');

        $stored = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            if ($this->pageCount($property) >= self::MAX_PAGES) {
                break;
            }

            $extension = $file->getClientOriginalExtension() ?: 'bin';

            $path = $file->storeAs(
                "properties/{$property->id}/documents",
                Str::random(32).'.'.$extension,
                self::DISK,
            );

            $property->documents()->create([
                'uploaded_by_user_id' => $uploader->id,
                'kind' => self::KIND_LEASE,
                'disk' => self::DISK,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'page_number' => ++$nextPage,
                // No scanner is wired up, on this path or the case photo
                // path. Recorded as Skipped rather than Clean so the
                // column never claims a check that did not happen.
                'scan_status' => ScanStatus::Skipped,
                'uploaded_at' => now(),
            ]);

            $stored++;
        }

        return $stored;
    }

    public function pageCount(Property $property): int
    {
        return PropertyDocument::query()
            ->where('property_id', $property->id)
            ->where('kind', self::KIND_LEASE)
            ->count();
    }

    /**
     * Validation rules for the file input, shared by both forms so the
     * two can never drift apart.
     *
     * @return array<int, string>
     */
    public static function fileRules(): array
    {
        return [
            'file',
            // PDF as well as images: a tenancy agreement is as often
            // emailed as a PDF as photographed, and refusing the format
            // someone already has is friction for nothing.
            'mimes:jpg,jpeg,png,webp,heic,heif,pdf',
            'max:4096',
        ];
    }
}
