<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D12 — single-use signed-login token. Carried by tenant-bound
 * outbound emails; clicking the magic link authenticates the tenant
 * and lands them on the case page.
 *
 * Created by MagicLinkGenerator at mail-render time. Consumed by
 * MagicLinkController::consume on click — sets used_at, runs
 * Auth::login, redirects to the case (or login on expired/used).
 *
 * Expiry: 7 days hardcoded (per D0.7 — short enough that forgotten
 * links rotate out, long enough that "I'll get to it next weekend"
 * still works). Single-use via used_at non-null check.
 */
class MagicLoginToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'purpose',
        'case_id',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(RepairCase::class, 'case_id');
    }

    public function isConsumed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
