<?php

/*
|--------------------------------------------------------------------------
| Inbound enquiries — non-case mail arriving on the Mailgun inbound domain
|--------------------------------------------------------------------------
|
| Mailgun's free tier allows ONE inbound route, and it is spent on the
| case-reply catch-all. So every address on the inbound domain reaches
| MailgunInboundController, and the discrimination happens here in the
| application rather than in the Mailgun dashboard.
|
| See docs/cc-brief-inbound-enquiries.md.
*/

return [

    /*
    | Where recognised enquiries are forwarded. A PRIVATE mailbox — set per
    | environment in .env, deliberately NO default. Unset means enquiries are
    | logged and dropped rather than sent somewhere unintended.
    */
    'forward_to' => env('MAIL_ENQUIRY_FORWARD_TO'),

    /*
    | The local parts recognised as enquiries, on the inbound domain only.
    | Comma-separated in env so a fourth address costs an env change and a
    | config:cache, not a deploy. Production defaults, per the CLAUDE.md
    | convention for Mailgun config.
    |
    | None of these can collide with a case reply: reply tokens are exactly
    | 20 alphanumeric characters.
    */
    'local_parts' => env('MAIL_ENQUIRY_LOCAL_PARTS', 'landlord-enquiries,privacy,info'),

];
