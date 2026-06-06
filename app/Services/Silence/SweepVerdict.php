<?php

namespace App\Services\Silence;

use App\Enums\BallPosition;
use App\Models\LetterTemplate;

/**
 * Pure-data result of evaluating a single case at a single moment.
 * Mirrors the silence_shadow_log row that will be written for it.
 *
 * Built by SilenceClock::evaluate. Never instantiated outside the
 * silence-model code path.
 */
final readonly class SweepVerdict
{
    public function __construct(
        public IntendedAction $intendedAction,
        public ?BallPosition $ballWith,
        public ?int $silenceDays,
        public ?LetterTemplate $intendedLetterTemplate,
        public ?int $escalationCounterValue,
        public ?int $nudgeNumber,
        public string $reasoning,
    ) {}
}
