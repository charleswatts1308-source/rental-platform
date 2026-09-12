<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UK postcode existence + place-name lookup, against postcodes.io.
 *
 * #51. Free, UK-wide, no API key, no registration, open data.
 *
 * THREE RULES THIS CLASS EXISTS TO ENFORCE.
 *
 * 1. IT FAILS OPEN. A timeout, a 5xx, a DNS failure or a malformed body
 *    all return UNKNOWN, never "invalid". A tenant with a broken boiler
 *    must not be blocked from registering their property because a
 *    third-party address-tidying service is down. Only a definite 404 —
 *    the service answered, and says no such postcode — is a NO.
 *
 * 2. IT NEVER OVERRULES THE TENANT. The lookup returns
 *    `admin_district`, which is the LOCAL AUTHORITY, not the Royal Mail
 *    post town. RG1 5RD is Reading as a district and may legitimately be
 *    given as Henley by someone who lives there. So the district is a
 *    SUGGESTION. The tenant lives there; they are the authority on their
 *    own address.
 *
 * 3. IT CACHES. Postcodes do not move. A result is worth keeping for a
 *    long time, and the cache also blunts repeated lookups on a form
 *    the tenant is editing.
 */
class PostcodeLookup
{
    /**
     * Definite answers only. Absence of an answer is UNKNOWN, not false.
     */
    public const EXISTS = 'exists';

    public const NOT_FOUND = 'not_found';

    public const UNKNOWN = 'unknown';

    /**
     * Look a postcode up.
     *
     * @return array{status: string, postcode: ?string, district: ?string}
     */
    public function lookup(string $postcode): array
    {
        $key = $this->normalise($postcode);

        if ($key === '') {
            return $this->unknown();
        }

        if (! config('services.postcodes.enabled')) {
            return $this->unknown();
        }

        // Only definite answers are cached. An UNKNOWN is a transient
        // condition — caching it would extend an outage well past its end.
        $cached = Cache::get($this->cacheKey($key));

        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->fetch($key);

        if ($result['status'] !== self::UNKNOWN) {
            Cache::put(
                $this->cacheKey($key),
                $result,
                now()->addDays((int) config('services.postcodes.cache_days', 30)),
            );
        }

        return $result;
    }

    /**
     * The narrow question the validator asks: is this definitely not a
     * real postcode? Anything short of a definite NO passes.
     */
    public function isDefinitelyUnknown(string $postcode): bool
    {
        return $this->lookup($postcode)['status'] === self::NOT_FOUND;
    }

    private function fetch(string $postcode): array
    {
        try {
            $response = Http::timeout((float) config('services.postcodes.timeout', 3))
                ->connectTimeout((float) config('services.postcodes.connect_timeout', 2))
                ->acceptJson()
                ->get(rtrim((string) config('services.postcodes.base_url'), '/')
                    .'/postcodes/'.rawurlencode($postcode));
        } catch (\Throwable $e) {
            // Deliberately swallowed. Logged at debug because a lookup
            // outage is not an application error and must not page anyone.
            Log::debug('Postcode lookup failed', [
                'postcode' => $postcode,
                'reason' => $e->getMessage(),
            ]);

            return $this->unknown();
        }

        // A definite 404 is the ONLY negative answer we accept. Every
        // other non-success is an outage as far as the tenant is
        // concerned.
        if ($response->status() === 404) {
            return [
                'status' => self::NOT_FOUND,
                'postcode' => null,
                'district' => null,
            ];
        }

        if (! $response->successful()) {
            return $this->unknown();
        }

        $result = $response->json('result');

        if (! is_array($result) || ! isset($result['postcode'])) {
            return $this->unknown();
        }

        return [
            'status' => self::EXISTS,
            'postcode' => (string) $result['postcode'],
            // admin_district is the LOCAL AUTHORITY. See rule 2 above.
            'district' => isset($result['admin_district'])
                ? (string) $result['admin_district']
                : null,
        ];
    }

    private function unknown(): array
    {
        return [
            'status' => self::UNKNOWN,
            'postcode' => null,
            'district' => null,
        ];
    }

    private function cacheKey(string $normalised): string
    {
        return 'postcode-lookup:'.$normalised;
    }

    private function normalise(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');
    }
}
