<?php

namespace App\Models;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Exceptions\InvalidCaseTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RepairCase extends Model
{
    /** @use HasFactory<\Database\Factories\RepairCaseFactory> */
    use HasFactory;

    protected $table = 'cases';

    private bool $inTransition = false;

    protected static function booted(): void
    {
        static::created(function (self $case): void {
            $case->events()->create([
                'event_type' => 'case_opened',
                'actor_user_id' => $case->tenant_user_id,
                'actor_label' => 'tenant',
                'occurred_at' => $case->opened_at ?? now(),
            ]);
        });

        static::updating(function (self $case): void {
            if ($case->isDirty('status') && ! $case->inTransition) {
                throw InvalidCaseTransitionException::directWrite();
            }
        });
    }

    protected $fillable = [
        'url_slug',
        'tenant_user_id',
        'property_id',
        'landlord_contact_id',
        'category_key',
        'severity',
        'status',
        'current_stage',
        'hold_until',
        'opened_at',
        'closed_at',
        'ball_with',
        'silence_clock_started_at',
        'silence_settings_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'severity' => CaseSeverity::class,
            'status' => CaseStatus::class,
            'current_stage' => 'integer',
            'hold_until' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'silence_clock_started_at' => 'datetime',
            'silence_settings_snapshot' => 'array',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(RepairCategory::class, 'category_key', 'key');
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

    public function shadowLogs(): HasMany
    {
        return $this->hasMany(SilenceShadowLog::class, 'case_id');
    }

    /**
     * Permitted status transitions: from-status => [to-status => primary-event-type].
     * Any (from, to) pair not present here is illegal and rejected by transitionTo().
     * The event_type is the canonical status-change marker for that transition;
     * later phases may write peripheral events (token_issued, notice_sent, etc.)
     * alongside without changing the state machine.
     */
    private const TRANSITIONS = [
        'open' => [
            'awaiting_landlord' => 'notice_sent',
        ],
        'awaiting_landlord' => [
            // 'tenant_action_required' transition removed in silence-phase-2b:
            // SweepEscalations was its only driver and is demolished. Silence
            // sweep auto-escalation now SENDS WITHOUT TRANSITIONING — case
            // stays awaiting_landlord, clock restarts, ratchet advances via
            // the new case_messages row. See SendCaseNotice $isAutoEscalation
            // branch.
            'awaiting_tenant_review' => 'inbound_received',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
        ],
        'awaiting_tenant_review' => [
            'tenant_action_required' => 'escalation_eligible',
            'on_hold' => 'hold_set',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
        ],
        'tenant_action_required' => [
            'awaiting_landlord' => 'stage_advanced',
            'on_hold' => 'hold_set',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
            'dormant' => 'case_dormant',
        ],
        'on_hold' => [
            'tenant_action_required' => 'hold_expired',
            'awaiting_tenant_review' => 'inbound_received',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
        ],
        'dormant' => [
            'tenant_action_required' => 'tenant_re_engaged',
            'awaiting_tenant_review' => 'inbound_received',
            'abandoned' => 'case_abandoned',
        ],
        // resolved and abandoned are terminal — no allowed transitions out.
        'resolved' => [],
        'abandoned' => [],
    ];

    public static function isTransitionAllowed(CaseStatus $from, CaseStatus $to): bool
    {
        return array_key_exists($to->value, self::TRANSITIONS[$from->value] ?? []);
    }

    public function transitionTo(CaseStatus $newStatus, array $context = []): void
    {
        $oldStatus = $this->status;

        if (! self::isTransitionAllowed($oldStatus, $newStatus)) {
            throw InvalidCaseTransitionException::illegalTransition($oldStatus, $newStatus);
        }

        DB::transaction(function () use ($oldStatus, $newStatus, $context) {
            $this->applyColumnSideEffects($oldStatus, $newStatus, $context);
            $this->status = $newStatus;
            $this->inTransition = true;
            try {
                $this->save();
            } finally {
                $this->inTransition = false;
            }

            $this->events()->create([
                'event_type' => $context['event_type_override']
                    ?? self::TRANSITIONS[$oldStatus->value][$newStatus->value],
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'actor_label' => $context['actor_label'] ?? 'system',
                'occurred_at' => now(),
                'meta' => $context['meta'] ?? null,
            ]);
        });
    }

    private function applyColumnSideEffects(CaseStatus $oldStatus, CaseStatus $newStatus, array $context): void
    {
        if (in_array($newStatus, [CaseStatus::Resolved, CaseStatus::Abandoned], true)) {
            $this->closed_at = now();
        }

        if ($newStatus === CaseStatus::OnHold) {
            $this->hold_until = $context['hold_until'] ?? null;
        }

        if ($oldStatus === CaseStatus::TenantActionRequired && $newStatus === CaseStatus::AwaitingLandlord) {
            $this->current_stage = $this->current_stage + 1;
        }
    }
}
