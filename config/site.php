<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    |
    | The service's public name and domain, set once per environment. Every
    | page, email and letter reads them through App\Support\Site, so a rename
    | is a change of values, not of code (docs/domain-change-plan.md).
    |
    | Required per-environment, deliberately no default — same rule as the
    | Mailgun keys in services.php: an environment that forgets them fails
    | loudly instead of presenting itself under a real-looking name.
    |
    | Mail addresses are NOT derived from these; they stay on the Mailgun
    | settings in services.php.
    |
    */

    'name' => env('SITE_NAME'),

    'domain' => env('SITE_DOMAIN'),

];
