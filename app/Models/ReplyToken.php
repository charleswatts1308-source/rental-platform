<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplyToken extends Model
{
    /** @use HasFactory<\Database\Factories\ReplyTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'case_id',
        'token',
        'bound_email',
        'issued_at',
        'expires_at',
        'superseded_at',
        'use_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'superseded_at' => 'datetime',
            'last_used_at' => 'datetime',
            'use_count' => 'integer',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(RepairCase::class, 'case_id');
    }
}
