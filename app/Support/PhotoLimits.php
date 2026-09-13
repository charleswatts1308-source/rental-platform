<?php

namespace App\Support;

/**
 * The photo limits, in one place.
 *
 * Extracted from CaseController for #57. The 413 page has to state the
 * same two figures the form advertises, and it cannot reach a private
 * constant on a controller — and hardcoding them on an error page would
 * be the #58 mistake in a new location: a number nobody watches, quietly
 * drifting away from the one the machine enforces.
 *
 * Both figures are derived from PHP's own configuration at call time.
 * The CLI and the web SAPI load different ini files, which is why the
 * server-side validation rule stays the fixed constant and only display
 * and client-side behaviour use these.
 */
class PhotoLimits
{
    /**
     * Our own per-file cap, before PHP's is taken into account.
     */
    public const PER_FILE_KB = 4096;

    /**
     * The per-file size the machine will ACTUALLY accept, in bytes.
     *
     * min(our cap, PHP's upload_max_filesize). A form advertising 4MB on
     * a box configured for 2M promises something that cannot happen —
     * the #41 lesson turned on our own UI: never display a limit the
     * machine will not honour.
     */
    public static function perFileBytes(): int
    {
        $ours = self::PER_FILE_KB * 1024;
        $php = FileSize::fromIniShorthand(ini_get('upload_max_filesize'));

        return $php > 0 ? min($ours, $php) : $ours;
    }

    /**
     * The budget for the WHOLE selection, in bytes. #58.
     *
     * PHP refuses a multipart body over post_max_size before any
     * validation runs, so an over-budget selection costs the tenant the
     * entire submission. The reserve covers the description, the
     * landlord fields and multipart overhead.
     *
     * Returns 0 when post_max_size is unlimited or unreadable, which
     * every caller must read as "no total limit" rather than "a limit of
     * nothing".
     */
    public static function totalBytes(): int
    {
        $postMax = FileSize::fromIniShorthand(ini_get('post_max_size'));

        if ($postMax <= 0) {
            return 0;
        }

        return max(0, $postMax - self::RESERVE_BYTES);
    }

    /**
     * Headroom left for everything that is not a photo. A megabyte is
     * cheap insurance against a limit this codebase does not set and
     * cannot watch.
     */
    private const RESERVE_BYTES = 1024 * 1024;

    public static function perFileLabel(): string
    {
        return FileSize::human(self::perFileBytes());
    }

    /**
     * The total, for display. Deliberately the RAW post_max_size rather
     * than the reserved budget: it is the number a tenant can check
     * against their own files, and quoting a figure a megabyte under the
     * real one invites "but mine is smaller than that".
     */
    public static function totalLabel(): string
    {
        $postMax = FileSize::fromIniShorthand(ini_get('post_max_size'));

        return $postMax > 0 ? FileSize::human($postMax) : 'the server limit';
    }
}
