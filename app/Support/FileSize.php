<?php

namespace App\Support;

/**
 * One place to turn a byte count into something a tenant reads.
 *
 * Shared deliberately. Attachment sizes are shown in three places — the
 * create form, the D13 preview, and the case page message card — and the
 * whole point of the attachment work is that those three never disagree
 * about what is attached. A private format in each view is how they drift.
 *
 * The create form's JavaScript carries its own copy of this format (it
 * runs before anything reaches the server); keep the two in step.
 */
class FileSize
{
    private const KB = 1024;

    private const MB = 1048576;

    /**
     * Human-readable size. Below 1MB reads as whole KB; at or above 1MB
     * reads as MB to one decimal place.
     *
     * KB alone was fine when the per-file ceiling was 2MB. At 4MB a photo
     * renders as "3277 KB", which is a number nobody thinks in.
     */
    public static function human(int $bytes): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }

        if ($bytes >= self::MB) {
            return round($bytes / self::MB, 1).' MB';
        }

        return max(1, (int) round($bytes / self::KB)).' KB';
    }
}
