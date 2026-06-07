<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per case per silence:sweep evaluation (real or pretend).
 *
 * Shadow-mode artefact only — produced by silence:sweep, never written
 * by user-facing code paths. Old-model sweeps and the state machine
 * remain unaware of this table.
 *
 * intended_action vocabulary (fixed):
 *   - no_action
 *   - send_escalation              (intended_letter_template_id set)
 *   - send_nudge                   (intended_letter_template_id set)
 *   - transition_dormant_intent
 *   - transition_exhausted_intent  (Phase 4 will build the state)
 */
class SilenceShadowLog extends Model
{
    protected $table = 'silence_shadow_log';

    protected $fillable = [
        'case_id',
        'swept_at',
        'ball_with',
        'silence_days',
        'intended_action',
        'executed',
        'intended_letter_template_id',
        'escalation_counter_value',
        'nudge_number',
        'reasoning',
        'is_pretend',
        'pretend_today',
    ];

    protected function casts(): array
    {
        return [
            'swept_at' => 'datetime',
            'silence_days' => 'integer',
            'escalation_counter_value' => 'integer',
            'nudge_number' => 'integer',
            'executed' => 'boolean',
            'is_pretend' => 'boolean',
            'pretend_today' => 'date',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(RepairCase::class, 'case_id');
    }

    public function intendedLetterTemplate(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'intended_letter_template_id');
    }
}
