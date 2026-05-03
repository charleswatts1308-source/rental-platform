<?php

namespace App\Models;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairCase extends Model
{
    /** @use HasFactory<\Database\Factories\RepairCaseFactory> */
    use HasFactory;

    protected $table = 'cases';

    protected $fillable = [
        'url_slug',
        'tenant_user_id',
        'property_id',
        'landlord_contact_id',
        'category',
        'severity',
        'status',
        'current_stage',
        'next_stage_eligible_at',
        'hold_until',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => CaseSeverity::class,
            'status' => CaseStatus::class,
            'current_stage' => 'integer',
            'next_stage_eligible_at' => 'datetime',
            'hold_until' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_user_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlordContact(): BelongsTo
    {
        return $this->belongsTo(LandlordContact::class);
    }
}
