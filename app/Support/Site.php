<?php

namespace App\Support;

use RuntimeException;

/**
 * The service's own name and domain (config/site.php). The single read
 * path for both, so nothing in the application spells either out.
 *
 * Throws when a value is blank rather than falling back: a box with no
 * identity configured must not render pages or send mail under a guessed
 * one.
 *
 * Not to be confused with the HOST BADGE in layouts/app.blade.php, which
 * must keep showing request()->getHost(). On gafol SITE_DOMAIN is the brand
 * domain, and the badge exists to tell staging from production.
 */
class Site
{
    /** Who is speaking, e.g. "Dear Landlord". */
    public static function name(): string
    {
        return self::required('name', 'SITE_NAME');
    }

    /** Where to go, e.g. "dearlandlord.co.uk". Bare host, no scheme. */
    public static function domain(): string
    {
        return self::required('domain', 'SITE_DOMAIN');
    }

    private static function required(string $key, string $envName): string
    {
        $value = trim((string) config("site.{$key}"));

        if ($value === '') {
            throw new RuntimeException(
                "{$envName} is not configured. Set it in this environment's .env — "
                .'it has no default by design (config/site.php).'
            );
        }

        return $value;
    }
}
