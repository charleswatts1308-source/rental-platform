<?php

namespace App\Models;

use App\Enums\LandlordContactRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordContact extends Model
{
    /** @use HasFactory<\Database\Factories\LandlordContactFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'role',
        'organisation_name',
        'invited_by_user_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => LandlordContactRole::class,
            'verified_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
