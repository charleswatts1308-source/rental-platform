<?php

namespace App\Rules;

use App\Services\PostcodeLookup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * #51. Refuses a postcode the lookup service definitely does not know.
 *
 * FAILS OPEN BY DESIGN. This rule only rejects on a definite NOT_FOUND —
 * the service answered and said no such postcode. A timeout, an outage,
 * a malformed response or a disabled lookup all PASS, leaving the shape
 * regex as the only check, exactly as before #51.
 *
 * The reason is not politeness. A tenant with a broken boiler must never
 * be unable to register their property because an address-tidying
 * service is having a bad afternoon.
 */
class PostcodeIsReal implements ValidationRule
{
    public function __construct(private PostcodeLookup $lookup) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // 'required' owns this case.
        }

        if ($this->lookup->isDefinitelyUnknown($value)) {
            $fail('That postcode does not appear to exist. Please check it.');
        }
    }
}
