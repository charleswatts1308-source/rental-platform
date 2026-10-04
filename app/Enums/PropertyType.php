<?php

namespace App\Enums;

/**
 * What kind of dwelling the tenant rents. INFORMATION ONLY — ruled
 * 4 Oct 2026. It drives no letter, no obligation and no branch in the
 * escalation ladder; it exists so the sector we actually serve can be
 * counted. If anyone proposes reading this in a letter, that ruling has
 * to be revisited first.
 *
 * THE ORDER AND THE CATEGORIES ARE BOTH DELIBERATE and come from the
 * Background page (/prs), which publishes the English PRS distribution
 * from a named source. Matching those categories is the whole point:
 * it lets renters.rent's own figures be read against the national ones.
 *
 *   Terraced 34% · Purpose-built flat 29% · Semi-detached 16%
 *   Converted flat 12% · Detached 5% · Bungalow 3%
 *
 * Two consequences that look like mistakes and are not:
 *
 *  - FLATS ARE SPLIT, purpose-built from converted, because the source
 *    splits them. Together they are 41% of the sector — the largest
 *    distinction in the data. Collapsing them into one "Flat" throws
 *    that away.
 *  - THERE IS NO "END OF TERRACE". The source folds it into terraced.
 *    Offering it would move an unknown slice of the 34% into a category
 *    the national figures do not have, and the comparison stops working.
 *
 * The four below Bungalow are not separated at source but a tenant will
 * look for them. RoomInSharedHouse is kept in plain English rather than
 * labelled "HMO": whether a house IS an HMO is a legal question about
 * someone else's property, and a tenant asked it will guess. This field
 * only asks what they can see from where they stand.
 */
enum PropertyType: string
{
    case Terraced = 'terraced';
    case PurposeBuiltFlat = 'purpose_built_flat';
    case SemiDetached = 'semi_detached';
    case ConvertedFlat = 'converted_flat';
    case Detached = 'detached';
    case Bungalow = 'bungalow';
    case RoomInSharedHouse = 'room_shared';
    case Studio = 'studio';
    case Maisonette = 'maisonette';
    case ParkHome = 'park_home';
    case Other = 'other';

    /**
     * Rows that pre-date the question. NEVER OFFERED IN THE DROPDOWN.
     *
     * It exists so "Other" keeps meaning genuinely other. Backfilling
     * into Other would merge "the tenant chose Other" with "we never
     * asked", and the statistics that justify this field could not tell
     * them apart afterwards.
     */
    case NotSpecified = 'not_specified';

    public function label(): string
    {
        return match ($this) {
            self::Terraced => 'Terraced house',
            self::PurposeBuiltFlat => 'Purpose-built flat',
            self::SemiDetached => 'Semi-detached house',
            self::ConvertedFlat => 'Converted flat',
            self::Detached => 'Detached house',
            self::Bungalow => 'Bungalow',
            self::RoomInSharedHouse => 'Room in a shared house',
            self::Studio => 'Studio flat',
            self::Maisonette => 'Maisonette',
            self::ParkHome => 'Park home',
            self::Other => 'Other',
            self::NotSpecified => 'Not specified',
        };
    }

    /**
     * What the form offers, in the order it offers it. NotSpecified is
     * excluded — a tenant must choose a real answer.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type) => $type !== self::NotSpecified,
        ));
    }
}
