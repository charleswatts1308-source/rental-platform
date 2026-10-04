<?php

namespace App\Enums;

/**
 * Answer to "Do you have a lease agreement (even if you can't find it)?"
 *
 * THE QUESTION IS ABOUT EXISTENCE, NOT POSSESSION. That is what the
 * parenthesis is doing, and it is why this is not derived from whether
 * a document has been uploaded. A tenant who has an agreement but
 * cannot lay hands on it answers Yes; the two facts are different and
 * both matter.
 *
 * DontKnow is not padding. A lodger, or anyone in an informal
 * arrangement, may genuinely not know — and a forced yes/no would
 * record that guess as a fact. Because this third answer exists, the
 * field can be mandatory without making anyone invent one (ruled
 * 4 Oct 2026).
 *
 * A tenant with no written agreement at all is in a materially
 * different position from one who has mislaid theirs, which is the
 * reason for asking.
 */
enum LeaseAgreementAnswer: string
{
    case Yes = 'yes';
    case No = 'no';
    case DontKnow = 'unknown';

    /**
     * Rows that pre-date the question. NEVER OFFERED IN THE FORM — see
     * PropertyType::NotSpecified for the same reasoning: a backfill must
     * not be mistakable for an answer somebody gave.
     */
    case NotSpecified = 'not_specified';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Yes',
            self::No => 'No',
            self::DontKnow => "I don't know",
            self::NotSpecified => 'Not specified',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $answer) => $answer !== self::NotSpecified,
        ));
    }
}
