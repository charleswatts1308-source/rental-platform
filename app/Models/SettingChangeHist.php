<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D16 / B3 — one row per settings value change (append-only audit log).
 * Self-contained: key, editor, old value, new value, when. No version,
 * no subject_type — separate from LetterTextChangeHistory by design.
 */
class SettingChangeHist extends Model
{
    protected $table = 'settings_change_hist';

    /** Append-only: Eloquent manages created_at only, never updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'setting_key',
        'edited_by_user_id',
        'old_value',
        'new_value',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
