<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D16 / A1 — one row per letter-template edit (append-only version trail).
 * Carries the full before/after subject + body so the wording-of-record is
 * reconstructable. Written only by the admin template editor.
 */
class LetterTextChangeHistory extends Model
{
    protected $table = 'letter_text_change_history';

    /** Append-only: Eloquent manages created_at only, never updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'letter_template_id',
        'version',
        'edited_by_user_id',
        'before_subject',
        'after_subject',
        'before_body',
        'after_body',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
