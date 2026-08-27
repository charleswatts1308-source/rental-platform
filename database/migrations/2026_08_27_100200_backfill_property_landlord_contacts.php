<?php

use App\Support\BackfillPropertyLandlordContacts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration. Rebuilds each property's contact timeline from the
 * cases that used the old global rows, then repoints every case.
 *
 * cases.property_id is NOT NULL, so every case maps. landlord_contacts
 * rows with NO cases have no property and therefore no destination —
 * they are left behind here and discarded with the table in the final
 * commit. The count is reported at migrate time so it lands in the
 * deploy log rather than disappearing silently.
 *
 * The logic lives in App\Support\BackfillPropertyLandlordContacts so it
 * can be tested; this file only supplies the legacy rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landlord_contacts')) {
            return;
        }

        $rows = DB::table('cases')
            ->join('landlord_contacts', 'cases.landlord_contact_id', '=', 'landlord_contacts.id')
            ->orderBy('cases.property_id')
            ->orderBy('cases.opened_at')
            ->orderBy('cases.id')
            ->get([
                'cases.id as case_id',
                'cases.property_id',
                'cases.opened_at',
                'landlord_contacts.email',
                'landlord_contacts.name',
                'landlord_contacts.role',
                'landlord_contacts.organisation_name',
                'landlord_contacts.invited_by_user_id',
            ]);

        $result = (new BackfillPropertyLandlordContacts)($rows);

        $orphans = DB::table('landlord_contacts')
            ->whereNotIn('id', DB::table('cases')->select('landlord_contact_id'))
            ->count();

        if ($result['cases_repointed'] === 0 && $orphans === 0) {
            return;
        }

        echo sprintf(
            "  backfill: %d versions across %d properties, %d cases repointed, %d orphan contacts will be discarded with the table\n",
            $result['versions_created'],
            $result['properties_touched'],
            $result['cases_repointed'],
            $orphans,
        );
    }

    public function down(): void
    {
        DB::table('cases')->update(['property_landlord_contact_id' => null]);
        DB::table('property_landlord_contacts')->where('source', 'backfilled')->delete();
    }
};
