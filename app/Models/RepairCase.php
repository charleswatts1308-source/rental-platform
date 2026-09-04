<?php

namespace App\Models;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Enums\ExhaustedStance;
use App\Exceptions\InvalidCaseTransitionException;
use Carbon\CarbonInterface;
use Database\Factories\RepairCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RepairCase extends Model
{
    /** @use HasFactory<RepairCaseFactory> */
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
        'property_landlord_contact_id',
        'category_key',
        'severity',
        'description',
        'status',
        'current_stage',
        'hold_until',
        'opened_at',
        'closed_at',
        'dormant_at',
        'ball_with',
        'landlord_engaged',
        'exhausted_stance',
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
            'dormant_at' => 'datetime',
            'landlord_engaged' => 'boolean',
            'exhausted_stance' => ExhaustedStance::class,
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

    /**
     * PROVENANCE ONLY — the contact version this case opened with.
     *
     * Never use this to decide where a letter goes. Routing resolves the
     * property's CURRENT contact (see landlordRecipient), or correcting a
     * mistyped address would never reach the case's next letter, which is
     * exactly the hole snag #24 describes.
     */
    public function propertyLandlordContact(): BelongsTo
    {
        return $this->belongsTo(PropertyLandlordContact::class);
    }

    /**
     * The live routing target: who the NEXT letter on this case goes to.
     *
     * Nullable for display. Anything that actually sends should use
     * requireLandlordRecipient() instead.
     */
    public function landlordRecipient(): ?PropertyLandlordContact
    {
        return $this->property?->currentLandlordContact;
    }

    /**
     * As landlordRecipient(), but refuses to continue without one.
     *
     * A case with no current contact is broken data, and the failure mode
     * without this is a confusing error deep in the mailer. Sending a
     * letter to nobody is worse than failing loudly.
     */
    public function requireLandlordRecipient(): PropertyLandlordContact
    {
        $contact = $this->landlordRecipient();

        if (! $contact) {
            throw new \RuntimeException(
                "Case {$this->url_slug} has no current landlord contact on property {$this->property_id}."
            );
        }

        return $contact;
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
     * The two fully-terminal statuses: closed for good, no transitions out
     * (see TRANSITIONS — both map to []). escalation_exhausted is NOT here:
     * it is revivable + closable (D14). Used by the state-aware display
     * predicate, never to gate a transition (transitionTo owns that).
     */
    public function isClosed(): bool
    {
        // Snag #47 — delegates to an exhaustive match on the enum, so a new
        // status cannot silently inherit "not closed" without a decision.
        return $this->status->isClosedStatus();
    }

    /**
     * State-aware display predicate (#14 / #15 / #21-tail). Single source of
     * truth for case-card display rules, shared by the tenant case page and
     * the admin oversight view so the two can never drift.
     *
     * "Next escalation" is meaningful only while the landlord clock is
     * actively counting down: status awaiting_landlord, ball with landlord,
     * a clock-start, and a known interval. This suppresses it on on_hold
     * (#14, paused), on tenant-ball / dormant, and on every closed/exhausted
     * state (#21-tail). The D15 authorisation-pending refinement is ANDed in
     * by the tenant view (it owns that view-only flag).
     */
    public function showsNextEscalation(): bool
    {
        return $this->status === CaseStatus::AwaitingLandlord
            && $this->ball_with === 'landlord'
            && $this->silence_clock_started_at !== null
            && isset($this->silence_settings_snapshot['escalation.interval_days']);
    }

    /**
     * The projected next-escalation date, or null when not applicable.
     * Reads the per-case snapshot interval — the value actually governing
     * this case (B2: in-flight cases keep their clock-start snapshot).
     */
    public function nextEscalationDate(): ?CarbonInterface
    {
        if (! $this->showsNextEscalation()) {
            return null;
        }

        return $this->silence_clock_started_at
            ->copy()
            ->addDays((int) $this->silence_settings_snapshot['escalation.interval_days']);
    }

    /**
     * hold_until is a live pause only while the case is actually on_hold
     * (#15) — after the sweep releases the hold the column is kept as a
     * historical record but must not display as an active pause.
     */
    public function showsHoldUntil(): bool
    {
        return $this->status === CaseStatus::OnHold && $this->hold_until !== null;
    }

    /**
     * Permitted status transitions: from-status => [to-status => primary-event-type].
     * Any (from, to) pair not present here is illegal and rejected by transitionTo().
     * The event_type is the canonical status-change marker for that transition;
     * later phases may write peripheral events (token_issued, notice_sent, etc.)
     * alongside without changing the state machine.
     *
     * Post Phase 3:
     * - tenant_action_required is demolished (D0.3 Option A — see the
     *   drop_tenant_action_required_from_cases_status_enum migration).
     * - silence:sweep auto-escalation continues to SEND WITHOUT TRANSITIONING
     *   from awaiting_landlord (clock restart + ratchet via the new
     *   case_messages row; see SendCaseNotice $isAutoEscalation branch).
     * - silence:sweep tenant-side fires nudges without transitioning (the
     *   nudge is mail-only, NO case_messages row; clock keeps running per
     *   Correction 1) and at the dormancy threshold transitions to dormant.
     * - silence:sweep hold-expiry absorbs SweepHolds: on_hold past hold_until
     *   transitions straight to awaiting_landlord — the landlord still owes
     *   a response (no TAR intermediate).
     * - Tenant replies transition (or self-target) to awaiting_landlord
     *   uniformly (D6 + D8): same canonical event `tenant_replied`,
     *   regardless of source state.
     * - Dormant cases revive via tenant reply within dormancy.revival_days
     *   (D11) — same `tenant_replied` event; the revival window is checked
     *   by the policy gate, not the state machine.
     *
     * Post D15 (engagement-gated escalation):
     * - awaiting_landlord gains a `dormant` edge. An engaged-then-quiet
     *   landlord case withholds escalation (silence:sweep returns
     *   SendAuthorisationNudge instead of SendEscalation); the held case
     *   is landlord-ball throughout (ruling 2). If the tenant never
     *   authorises, the authorise-nudge ladder walks and the case
     *   transitions awaiting_landlord -> dormant. landlord_engaged is a
     *   one-way flag set in HandleInboundReply, never reset.
     *
     * Post D14 (Phase 4 — escalation_exhausted; design doc D5):
     * - awaiting_landlord gains an `escalation_exhausted` edge. A
     *   NEVER-ENGAGED landlord who ignores the full ladder (counter >=
     *   max_notices, clock expired) is promoted from the long-shadowed
     *   transition_exhausted_intent to this terminal. Only never-engaged
     *   cases reach it — an engaged case is tenant-gated below max (D15)
     *   and goes dormant instead.
     * - escalation_exhausted is allow-reply (D5): a tenant web reply
     *   revives to awaiting_landlord (tenant_replied); a landlord email
     *   reply revives to awaiting_tenant_review (inbound_received, via
     *   HandleInboundReply, which flips landlord_engaged true). Both
     *   mirror dormant's revival split. The tenant may also still close
     *   the case (resolved / abandoned). No revival window (unlike
     *   dormancy) — a reply revives whenever it lands.
     */
    private const TRANSITIONS = [
        'open' => [
            'awaiting_landlord' => 'notice_sent',
        ],
        'awaiting_landlord' => [
            'awaiting_tenant_review' => 'inbound_received',
            'on_hold' => 'hold_set',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
            // D15 — engagement-gated escalation. An engaged-then-quiet
            // case withholds escalation for tenant authorisation; if the
            // tenant never authorises, the held case walks the
            // authorise-nudge ladder and goes dormant from here (the
            // case is landlord-ball throughout, per D15 ruling 2). This
            // edge is the unauthorised tail; D11 revival applies as for
            // any dormant case.
            'dormant' => 'case_dormant',
            // D14 — never-engaged ladder exhaustion (design doc D5). The
            // sweep fires the landlord closer then promotes the case here.
            'escalation_exhausted' => 'case_exhausted',
        ],
        'awaiting_tenant_review' => [
            'awaiting_landlord' => 'tenant_replied',
            'on_hold' => 'hold_set',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
            'dormant' => 'case_dormant',
        ],
        'on_hold' => [
            // Tenant reply from on_hold IS the resume action (D8) — same
            // canonical event as any other tenant reply.
            'awaiting_landlord' => 'tenant_replied',
            'awaiting_tenant_review' => 'inbound_received',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
        ],
        'dormant' => [
            // Revival within dormancy.revival_days; the window check is in
            // the policy gate.
            'awaiting_landlord' => 'tenant_replied',
            'awaiting_tenant_review' => 'inbound_received',
            'resolved' => 'case_resolved',
            'abandoned' => 'case_abandoned',
        ],
        'escalation_exhausted' => [
            // D14 allow-reply (design doc D5), mirroring dormant's split:
            // tenant web reply revives the active correspondence; landlord
            // email reply (via HandleInboundReply) revives to review and
            // flips landlord_engaged true. No revival window. The tenant
            // may still deliberately close the case.
            'awaiting_landlord' => 'tenant_replied',
            'awaiting_tenant_review' => 'inbound_received',
            'resolved' => 'case_resolved',
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

        // D11 anchor: stamp dormant_at when a case enters dormant. The
        // revival window (dormancy.revival_days) is measured from this
        // timestamp, not from silence_clock_started_at.
        if ($newStatus === CaseStatus::Dormant) {
            $this->dormant_at = now();
        }

        // Clear dormant_at on revival so a future re-entry to dormant
        // gets a fresh anchor. Revival is any transition OUT of dormant.
        if ($oldStatus === CaseStatus::Dormant && $newStatus !== CaseStatus::Dormant) {
            $this->dormant_at = null;
        }
    }
}
