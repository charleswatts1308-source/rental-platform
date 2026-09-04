<?php

namespace App\Enums;

/**
 * Provenance of a property_landlord_contacts row.
 *
 * Backfilled rows had their effective_from RECONSTRUCTED from the
 * opened_at of the cases that used them — it is an inference, not an
 * observed change. The contact-history view must present the two
 * differently, or the first thing the history shows a user is a change
 * nobody actually made.
 */
enum ContactSource: string
{
    case Entered = 'entered';
    case Backfilled = 'backfilled';
}
