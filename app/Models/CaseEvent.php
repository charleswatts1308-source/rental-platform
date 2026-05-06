<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseEvent extends Model
{
    /** @use HasFactory<\Database\Factories\CaseEventFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'case_id',
        'event_type',
        'actor_user_id',
        'actor_label',
        'occurred_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(RepairCase::class, 'case_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
