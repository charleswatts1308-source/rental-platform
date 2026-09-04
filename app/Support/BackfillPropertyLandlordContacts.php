<?php

namespace App\Support;

use App\Enums\ContactSource;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill: rebuild each property's landlord-contact timeline
 * from the cases that used the old global landlord_contacts rows.
 *
 * WHY THIS IS A CLASS AND NOT INLINE IN THE MIGRATION
 * Migrations are not reachable by the test suite, and this is the one
 * piece of the change that runs exactly once against real data with no
 * second chance. Extracting it is the only way it gets a test.
 *
 * WHY IT TAKES ROWS RATHER THAN QUERYING
 * landlord_contacts is dropped in the final commit. If this class read
 * that table directly, its test would become unrunnable the moment the
 * table went — and a fresh `migrate` would still need this code to work.
 * Taking the legacy rows as an argument keeps the logic testable forever
 * with synthetic input, and leaves the one-off query in the migration
 * where it belongs.
 *
 * WHAT IT PRODUCES
 * Per property, cases in opened_at order. Each time the contact email
 * changes from the previous case's, the version in force is closed at
 * that case's opened_at and a new one opened. Every case is repointed to
 * the version in force when it was raised.
 *
 * The resulting effective_from values are a RECONSTRUCTION, not observed
 * history — the system never recorded when a landlord's address actually
 * changed, only which address each case used. Every row this writes is
 * therefore stamped source = backfilled, so the contact-history view can
 * present it as inferred rather than as an edit somebody made.
 *
 * It does not read, write or touch case_messages. The frozen record of
 * what was already sent is not this class's business.
 */
final class BackfillPropertyLandlordContacts
{
    /**
     * @param  iterable<object>  $legacyRows  case_id, property_id, opened_at,
     *                                        email, name, role,
     *                                        organisation_name,
     *                                        invited_by_user_id
     * @return array{versions_created: int, cases_repointed: int, properties_touched: int}
     */
    public function __invoke(iterable $legacyRows): array
    {
        $byProperty = [];

        foreach ($legacyRows as $row) {
            $byProperty[(int) $row->property_id][] = $row;
        }

        $versionsCreated = 0;
        $casesRepointed = 0;

        foreach ($byProperty as $propertyId => $rows) {
            // Sort defensively rather than trusting the caller's ORDER BY:
            // getting this order wrong silently fabricates a different
            // timeline, and nothing downstream would notice.
            usort($rows, function ($a, $b) {
                return [$a->opened_at, (int) $a->case_id] <=> [$b->opened_at, (int) $b->case_id];
            });

            $currentVersionId = null;
            $currentEmail = null;

            foreach ($rows as $row) {
                $email = $this->normalise($row->email);

                if ($currentVersionId === null || $email !== $currentEmail) {
                    if ($currentVersionId !== null) {
                        DB::table('property_landlord_contacts')
                            ->where('id', $currentVersionId)
                            ->update([
                                'superseded_at' => $row->opened_at,
                                'is_current' => null,
                                'updated_at' => now(),
                            ]);
                    }

                    $currentVersionId = DB::table('property_landlord_contacts')->insertGetId([
                        'property_id' => $propertyId,
                        'email' => $email,
                        'name' => $row->name,
                        'role' => $row->role,
                        'organisation_name' => $row->organisation_name,
                        'created_by_user_id' => $row->invited_by_user_id,
                        'effective_from' => $row->opened_at,
                        'superseded_at' => null,
                        'is_current' => 1,
                        'source' => ContactSource::Backfilled->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $currentEmail = $email;
                    $versionsCreated++;
                }

                DB::table('cases')
                    ->where('id', $row->case_id)
                    ->update(['property_landlord_contact_id' => $currentVersionId]);

                $casesRepointed++;
            }
        }

        return [
            'versions_created' => $versionsCreated,
            'cases_repointed' => $casesRepointed,
            'properties_touched' => count($byProperty),
        ];
    }

    /**
     * Matches how the old CaseController::resolveLandlordContact stored
     * addresses, so "same email" here means the same thing it meant then.
     */
    private function normalise(string $email): string
    {
        return strtolower(trim($email));
    }
}
