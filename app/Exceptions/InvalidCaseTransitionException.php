<?php

namespace App\Exceptions;

use App\Enums\CaseStatus;
use RuntimeException;

class InvalidCaseTransitionException extends RuntimeException
{
    public static function illegalTransition(CaseStatus $from, CaseStatus $to): self
    {
        return new self(
            "Cannot transition case from '{$from->value}' to '{$to->value}'."
        );
    }

    public static function directWrite(): self
    {
        return new self(
            "Direct writes to RepairCase::status are not allowed; use transitionTo()."
        );
    }
}
