<?php

namespace App\Models;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class, 'case_id');
    }

    public function replyTokens(): HasMany
    {
        return $this->hasMany(ReplyToken::class, 'case_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class, 'case_id');
    }

    /**
     * Permitted status transitions: from-status value => list of to-status values.
     * Any (from, to) pair not present here is illegal and rejected by transitionTo().
     */
    private const ALLOWED_TRANSITIONS = [
        'open' => ['awaiting_landlord'],
        'awaiting_landlord' => [
            'awaiting_tenant_review',
            'tenant_action_required',
            'resolved',
            'abandoned',
        ],
        'awaiting_tenant_review' => [
            'tenant_action_required',
            'on_hold',
            'resolved',
            'abandoned',
        ],
        'tenant_action_required' => [
            'awaiting_landlord',
            'on_hold',
            'resolved',
            'abandoned',
            'dormant',
        ],
        'on_hold' => [
            'tenant_action_required',
            'awaiting_tenant_review',
            'resolved',
            'abandoned',
        ],
        'dormant' => [
            'tenant_action_required',
            'abandoned',
        ],
        // resolved and abandoned are terminal — no allowed transitions out.
        'resolved' => [],
        'abandoned' => [],
    ];

    public static function isTransitionAllowed(CaseStatus $from, CaseStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED_TRANSITIONS[$from->value] ?? [], true);
    }
}
