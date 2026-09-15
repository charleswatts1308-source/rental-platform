<?php

namespace App\Support;

use App\Models\Setting;

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
     * Default when the setting is absent.
     */
    public const COUNT_DEFAULT = 1;

    /**
     * The live attachment ceiling for letter 1.
     *
     * Read LIVE, never snapshotted — deliberately the opposite of the
     * escalation intervals (D4), because the whole purpose of this key is
     * reacting to a deliverability problem now, across cases already
     * running (docs/attachment-policy-design.md R3).
     *
     * Clamped to the range the admin surface offers, so a value edited
     * directly in the database cannot widen the ceiling past the design.
     *
     * Moved here from CaseController for #19: the reply form needs the
     * same number the create form uses, and a second copy of
     * "Setting::get, clamp to 0..3" in a view is how the two would come to
     * disagree about how many photos a tenant may attach.
     */
    public static function ceiling(): int
    {
        $value = Setting::get('attachments.first_notice_max', self::COUNT_DEFAULT);

        return max(0, min(3, (int) $value));
    }

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
