<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterTemplate extends Model
{
    protected $fillable = [
        'code',
        'description',
        'subject',
        'body',
        'type',
        'stage',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function caseMessages(): HasMany
    {
        return $this->hasMany(CaseMessage::class);
    }

    /**
     * D1 fallback lookup: pick the active escalation template for notice
     * number N, falling back to the active stage=NULL row if no per-stage
     * row exists. Returns null only if no escalation template is active
     * at all (a misconfiguration the caller must handle).
     */
    public static function forEscalation(int $stage): ?self
    {
        return self::query()
            ->where('type', 'escalation')
            ->where('active', true)
            ->where('stage', $stage)
            ->first()
            ?? self::query()
                ->where('type', 'escalation')
                ->where('active', true)
                ->whereNull('stage')
                ->first();
    }

    /**
     * Generic active-by-type lookup for nudge / exhaustion / notification
     * templates where there is at most one canonical active row. Returns
     * null when no active row exists — by design that means "do not send"
     * (per the optional-communication idiom in the design doc §3).
     */
    public static function firstActiveOfType(string $type): ?self
    {
        return self::query()
            ->where('type', $type)
            ->where('active', true)
            ->first();
    }
}
