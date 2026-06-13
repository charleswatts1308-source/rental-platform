<?php

namespace App\Console\Commands;

use App\Actions\SendCaseNotice;
use App\Enums\BallPosition;
use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use App\Models\Setting;
use App\Models\SilenceShadowLog;
use App\Services\LetterTemplateRenderer;
use App\Services\MagicLinkGenerator;
use App\Services\Silence\IntendedAction;
use App\Services\Silence\SilenceClock;
use App\Services\Silence\SweepVerdict;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Silence-model sweep — Phase 3: LANDLORD-SIDE LIVE (from 2b),
 * TENANT-SIDE LIVE (new), HOLD EXPIRY ABSORBED (new), pretend always
 * shadow.
 *
 * For every non-terminal case, asks SilenceClock what it WOULD do
 * and:
 *   - landlord-side send_escalation (real): executes the send via
 *     SendCaseNotice, restarts the clock, fires the
 *     auto_escalation_tenant_notice (active-row idiom), logs a
 *     shadow row with executed=true.
 *   - landlord-side transition_exhausted_intent: logs only (Phase 4
 *     wires the state).
 *   - tenant-side send_nudge (NEW live): catches up any missing
 *     nudges from the current silence window — Correction 1
 *     stipulates the clock does NOT restart on nudge, so dedup hangs
 *     on a count of nudge_sent case_events since
 *     silence_clock_started_at. If count < verdict.nudgeNumber, send
 *     (verdict.nudgeNumber - count) nudges in order. Each nudge
 *     queues mail + writes a nudge_sent event with meta.nudge_number.
 *     NO case_messages row (evidential invariant).
 *   - tenant-side transition_dormant_intent (NEW live):
 *     transitionTo(Dormant) — applyColumnSideEffects stamps
 *     dormant_at — and queues the dormancy_transition_notice mail.
 *     The dormancy notification IS the catch-up communication when
 *     the sweep was delayed past the nudge thresholds; missed nudges
 *     are not back-filled.
 *   - resume_from_hold (NEW): transitionTo(AwaitingLandlord) on
 *     OnHold cases past hold_until — landlord still owes a response,
 *     so ball returns to landlord (not to a TAR intermediate; TAR is
 *     demolished). Queues hold_expired_notice. The transition's
 *     applyColumnSideEffects clears nothing for hold_until — it
 *     stays as historical record.
 *   - --pretend-today: FORCES FULL SHADOW everywhere on every
 *     species; the execution gate is wrapped in (!$isPretend && ...).
 *
 * Per-case exception isolation: a throw inside one case's handling
 * does NOT abort the sweep.
 *
 * Race / double-fire guards: per-case lockForUpdate inside a
 * transaction. The post-lock comparison expands beyond 2b — for
 * sends/transitions we compare silence_clock_started_at AND status;
 * for resume_from_hold we compare hold_until AND status. If anything
 * moved between the verdict and the lock, we skip with a "superseded
 * by concurrent sweep" reasoning.
 *
 * Snag #10 fix: the summary tally is derived from the set of shadow
 * rows actually written by this invocation (collected by ID), not
 * by re-querying swept_at + is_pretend — which double-counted under
 * shared pretend_today swept_at.
 */
class SilenceSweep extends Command
{
    protected $signature = 'silence:sweep
        {--pretend-today= : Evaluate as if today were YYYY-MM-DD (local/staging/preprod only)}';

    protected $description = 'Silence sweep — landlord-side + tenant-side LIVE; hold expiry absorbed; --pretend-today forces full shadow';

    public function handle(
        SilenceClock $clock,
        SendCaseNotice $sendCaseNotice,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
    ): int {
        $now = $this->resolveNow();
        if ($now === null) {
            return self::FAILURE;
        }

        $isPretend = $this->option('pretend-today') !== null;
        $pretendToday = $isPretend ? $now->toDateString() : null;

        $cases = RepairCase::query()
            ->whereNotIn('status', [
                CaseStatus::Resolved,
                CaseStatus::Abandoned,
                CaseStatus::Dormant,
            ])
            ->get();

        $writtenRowIds = [];
        $executedCount = 0;
        $exceptionCount = 0;

        foreach ($cases as $case) {
            try {
                [$rowIds, $executed] = $this->handleCase(
                    $case,
                    $clock,
                    $sendCaseNotice,
                    $renderer,
                    $magicLinkGenerator,
                    $now,
                    $isPretend,
                    $pretendToday,
                );
                foreach ($rowIds as $id) {
                    $writtenRowIds[] = $id;
                }
                if ($executed) {
                    $executedCount++;
                }
            } catch (Throwable $e) {
                $id = $this->writeExceptionShadowRow($case, $e, $now, $isPretend, $pretendToday);
                $writtenRowIds[] = $id;
                $exceptionCount++;
            }
        }

        $written = SilenceShadowLog::query()
            ->whereIn('id', $writtenRowIds)
            ->get();
        $counts = [
            'no_action' => $written->where('intended_action', 'no_action')->count(),
            'send_escalation' => $written->where('intended_action', 'send_escalation')->count(),
            'send_nudge' => $written->where('intended_action', 'send_nudge')->count(),
            'send_authorisation_nudge' => $written->where('intended_action', 'send_authorisation_nudge')->count(),
            'transition_dormant_intent' => $written->where('intended_action', 'transition_dormant_intent')->count(),
            'transition_exhausted_intent' => $written->where('intended_action', 'transition_exhausted_intent')->count(),
            'resume_from_hold' => $written->where('intended_action', 'resume_from_hold')->count(),
        ];

        $this->line(sprintf(
            'silence:sweep %sevaluated %d case(s) at %s — actions: %s; executed=%d; exceptions=%d',
            $isPretend ? "(pretend={$pretendToday}) " : '',
            $cases->count(),
            $now->toDateTimeString(),
            implode(', ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($counts), $counts)),
            $executedCount,
            $exceptionCount,
        ));

        if (! $isPretend) {
            $this->cleanupPreviewPhotos($now);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, int>, 1: bool} list of shadow-row IDs written, executed flag
     */
    private function handleCase(
        RepairCase $case,
        SilenceClock $clock,
        SendCaseNotice $sendCaseNotice,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): array {
        $verdict = $clock->evaluate($case, $now);

        $shouldExecute = ! $isPretend && match ($verdict->intendedAction) {
            IntendedAction::SendEscalation => $verdict->ballWith === BallPosition::Landlord,
            IntendedAction::SendNudge => $verdict->ballWith === BallPosition::Tenant,
            // D15 — held authorise-nudge is landlord-ball (ruling 2).
            IntendedAction::SendAuthorisationNudge => $verdict->ballWith === BallPosition::Landlord,
            // Dormancy fires from the tenant-side ladder (ball=tenant) OR
            // the D15 held-escalation tail (ball=landlord). transitionTo's
            // edge-legality check is the real guard either way.
            IntendedAction::TransitionDormantIntent => in_array($verdict->ballWith, [BallPosition::Tenant, BallPosition::Landlord], true),
            IntendedAction::ResumeFromHold => true,
            default => false,
        };

        if (! $shouldExecute) {
            $id = $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: false);

            return [[$id], false];
        }

        return DB::transaction(function () use (
            $case, $sendCaseNotice, $renderer, $magicLinkGenerator,
            $now, $isPretend, $pretendToday, $verdict
        ) {
            $locked = RepairCase::query()->lockForUpdate()->find($case->id);
            if ($locked === null) {
                $id = $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: false);

                return [[$id], false];
            }

            $superseded = $this->detectSupersede($verdict, $case, $locked);
            if ($superseded !== null) {
                $id = $this->writeShadowRow($case->id, $superseded, $now, $isPretend, $pretendToday, executed: false);

                return [[$id], false];
            }

            return match ($verdict->intendedAction) {
                IntendedAction::SendEscalation => $this->executeEscalation(
                    $locked, $verdict, $sendCaseNotice, $renderer, $magicLinkGenerator,
                    $now, $isPretend, $pretendToday,
                ),
                IntendedAction::SendNudge => $this->executeNudge(
                    $locked, $verdict, $renderer, $magicLinkGenerator,
                    $now, $isPretend, $pretendToday,
                ),
                IntendedAction::SendAuthorisationNudge => $this->executeAuthorisationNudge(
                    $locked, $verdict, $renderer, $magicLinkGenerator,
                    $now, $isPretend, $pretendToday,
                ),
                IntendedAction::TransitionDormantIntent => $this->executeDormancyTransition(
                    $locked, $verdict, $renderer, $magicLinkGenerator,
                    $now, $isPretend, $pretendToday,
                ),
                IntendedAction::ResumeFromHold => $this->executeResumeFromHold(
                    $locked, $verdict, $renderer, $magicLinkGenerator,
                    $now, $isPretend, $pretendToday,
                ),
                default => [[$this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: false)], false],
            };
        });
    }

    /**
     * Post-lock supersede guard. Returns a synthesised verdict to log
     * if the case state moved between the pre-lock evaluation and the
     * locked re-read, or null if the case is unchanged.
     *
     * Witnesses checked:
     *   - status (any verdict)
     *   - silence_clock_started_at (send/nudge/transition verdicts)
     *   - hold_until (ResumeFromHold)
     */
    private function detectSupersede(
        SweepVerdict $verdict,
        RepairCase $preLock,
        RepairCase $locked,
    ): ?SweepVerdict {
        if ($preLock->status !== $locked->status) {
            return $this->supersededVerdict($verdict, 'status changed between verdict and lock');
        }

        if ($verdict->intendedAction === IntendedAction::ResumeFromHold) {
            if ($preLock->hold_until === null || $locked->hold_until === null
                || ! $locked->hold_until->equalTo($preLock->hold_until)) {
                return $this->supersededVerdict($verdict, 'hold_until changed between verdict and lock');
            }

            return null;
        }

        // Clock-based verdicts: SendEscalation, SendNudge, TransitionDormantIntent.
        if ($preLock->silence_clock_started_at === null || $locked->silence_clock_started_at === null
            || ! $locked->silence_clock_started_at->equalTo($preLock->silence_clock_started_at)) {
            return $this->supersededVerdict($verdict, 'clock restarted between verdict and lock');
        }

        return null;
    }

    private function supersededVerdict(SweepVerdict $verdict, string $why): SweepVerdict
    {
        return new SweepVerdict(
            intendedAction: IntendedAction::NoAction,
            ballWith: $verdict->ballWith,
            silenceDays: $verdict->silenceDays,
            intendedLetterTemplate: null,
            escalationCounterValue: $verdict->escalationCounterValue,
            nudgeNumber: null,
            reasoning: "superseded by concurrent sweep — {$why}",
        );
    }

    /**
     * @return array{0: array<int, int>, 1: bool}
     */
    private function executeEscalation(
        RepairCase $case,
        SweepVerdict $verdict,
        SendCaseNotice $sendCaseNotice,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): array {
        $sendCaseNotice->execute($case);
        $this->dispatchAutoEscalationTenantNotice($case->fresh(), $verdict, $renderer, $magicLinkGenerator);
        $id = $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: true);

        return [[$id], true];
    }

    /**
     * One nudge per sweep. SilenceClock has already done the
     * count-vs-thresholds reasoning and handed us a verdict whose
     * `nudgeNumber` is the next unsent rung. We just send it.
     *
     * The clock is NOT restarted (Correction 1) — silence keeps
     * accumulating from the original ball-flip until tenant reply
     * or dormancy lands.
     *
     * Race guard: re-count nudge_sent events under the lock. If the
     * count has moved since the pre-lock evaluation (concurrent sweep
     * fired the nudge), supersede.
     *
     * @return array{0: array<int, int>, 1: bool}
     */
    private function executeNudge(
        RepairCase $case,
        SweepVerdict $verdict,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): array {
        $nudgeNumber = (int) $verdict->nudgeNumber;
        $expectedPriorCount = $nudgeNumber - 1;

        $currentCount = $case->events()
            ->where('event_type', 'nudge_sent')
            ->where('occurred_at', '>=', $case->silence_clock_started_at)
            ->count();

        if ($currentCount !== $expectedPriorCount) {
            $supersededVerdict = $this->supersededVerdict(
                $verdict,
                "nudge_sent count changed ({$expectedPriorCount} → {$currentCount}); concurrent sweep fired the nudge",
            );
            $id = $this->writeShadowRow($case->id, $supersededVerdict, $now, $isPretend, $pretendToday, executed: false);

            return [[$id], false];
        }

        $this->dispatchNudge($case, $nudgeNumber, $renderer, $magicLinkGenerator);
        $case->events()->create([
            'event_type' => 'nudge_sent',
            'actor_label' => 'system',
            'occurred_at' => now(),
            'meta' => ['nudge_number' => $nudgeNumber, 'silence_days' => $verdict->silenceDays],
        ]);

        $id = $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: true);

        return [[$id], true];
    }

    /**
     * D15 — held authorise-nudge for an engaged-then-quiet landlord. The
     * escalation is withheld; this fires a private tenant-facing nudge
     * pointing at the authorise action, on the same cadence as ordinary
     * nudges. Mail-only — NO case_messages row, NO ball move, the clock is
     * NOT restarted (silence keeps accruing toward the dormancy tail).
     * Writes an `authorisation_nudge_sent` event so the ladder dedups,
     * distinct from the tenant-side `nudge_sent`.
     *
     * Race guard mirrors executeNudge: re-count under the lock.
     *
     * @return array{0: array<int, int>, 1: bool}
     */
    private function executeAuthorisationNudge(
        RepairCase $case,
        SweepVerdict $verdict,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): array {
        $nudgeNumber = (int) $verdict->nudgeNumber;
        $expectedPriorCount = $nudgeNumber - 1;

        $currentCount = $case->events()
            ->where('event_type', 'authorisation_nudge_sent')
            ->where('occurred_at', '>=', $case->silence_clock_started_at)
            ->count();

        if ($currentCount !== $expectedPriorCount) {
            $supersededVerdict = $this->supersededVerdict(
                $verdict,
                "authorisation_nudge_sent count changed ({$expectedPriorCount} → {$currentCount}); concurrent sweep fired the nudge",
            );
            $id = $this->writeShadowRow($case->id, $supersededVerdict, $now, $isPretend, $pretendToday, executed: false);

            return [[$id], false];
        }

        $withheldNotice = ($verdict->escalationCounterValue ?? 0) + 1;

        $this->dispatchAuthorisationNudge($case, $withheldNotice, $renderer, $magicLinkGenerator);
        $case->events()->create([
            'event_type' => 'authorisation_nudge_sent',
            'actor_label' => 'system',
            'occurred_at' => now(),
            'meta' => [
                'nudge_number' => $nudgeNumber,
                'withheld_notice' => $withheldNotice,
                'silence_days' => $verdict->silenceDays,
            ],
        ]);

        $id = $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: true);

        return [[$id], true];
    }

    /**
     * @return array{0: array<int, int>, 1: bool}
     */
    private function executeDormancyTransition(
        RepairCase $case,
        SweepVerdict $verdict,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): array {
        $case->transitionTo(CaseStatus::Dormant, [
            'actor_label' => 'system',
            'meta' => ['silence_days' => $verdict->silenceDays],
        ]);
        $this->dispatchTenantNotification(
            $case->fresh(),
            'dormancy_transition_notice',
            'dormancy_transition',
            $verdict->nudgeNumber,
            $renderer,
            $magicLinkGenerator,
        );

        $id = $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: true);

        return [[$id], true];
    }

    /**
     * @return array{0: array<int, int>, 1: bool}
     */
    private function executeResumeFromHold(
        RepairCase $case,
        SweepVerdict $verdict,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): array {
        // Restart the silence clock with a fresh snapshot — the
        // landlord owes a response from "now" (sweep moment).
        $case->ball_with = 'landlord';
        $case->silence_clock_started_at = now();
        $case->silence_settings_snapshot = SilenceClock::snapshotCurrentSettings();
        $case->transitionTo(CaseStatus::AwaitingLandlord, [
            'actor_label' => 'system',
            'event_type_override' => 'hold_expired',
            'meta' => ['hold_until' => $case->hold_until?->toIso8601String()],
        ]);
        $this->dispatchTenantNotification(
            $case->fresh(),
            'hold_expired_notice',
            'hold_expired',
            null,
            $renderer,
            $magicLinkGenerator,
        );

        $id = $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: true);

        return [[$id], true];
    }

    private function dispatchAutoEscalationTenantNotice(
        RepairCase $case,
        SweepVerdict $verdict,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
    ): void {
        $this->dispatchTenantNotification(
            $case,
            'auto_escalation_tenant_notice',
            'auto_escalation',
            $verdict->silenceDays,
            $renderer,
            $magicLinkGenerator,
        );
    }

    private function dispatchNudge(
        RepairCase $case,
        int $nudgeNumber,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
    ): void {
        $template = LetterTemplate::query()
            ->where('type', 'tenant_nudge')
            ->where('active', true)
            ->first();
        if ($template === null) {
            return;
        }

        $case->loadMissing(['tenant', 'property', 'landlordContact']);

        $magicLink = $magicLinkGenerator->mintUrl(
            $case->tenant,
            $case,
            'dormancy_nudge',
        );

        $rendered = $renderer->render($template, [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordContact->name ?: 'your landlord',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'issue_description' => $case->description,
            'response_days' => (int) Setting::get('escalation.interval_days', 14),
            'notice_number' => $case->current_stage,
            'nudge_number' => $nudgeNumber,
            'magic_link' => $magicLink,
        ]);

        Mail::to($case->tenant->email)->queue(new AutoEscalationTenantNotice(
            renderedSubject: $rendered['subject'],
            renderedBody: $rendered['body'],
        ));
    }

    /**
     * D15 — render the authorise-prompt nudge (active-row idiom: no active
     * `authorisation_required_nudge` row → skip silently) and queue it.
     * {{notice_number}} is the WITHHELD notice (counter + 1), and the
     * magic link lands the tenant on the case page where the authorise
     * prompt is shown. Mail-only — no case_messages row.
     */
    private function dispatchAuthorisationNudge(
        RepairCase $case,
        int $withheldNotice,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
    ): void {
        $template = LetterTemplate::query()
            ->where('type', 'tenant_notification')
            ->where('code', 'authorisation_required_nudge')
            ->where('active', true)
            ->first();
        if ($template === null) {
            return;
        }

        $case->loadMissing(['tenant', 'property', 'landlordContact']);

        $magicLink = $magicLinkGenerator->mintUrl(
            $case->tenant,
            $case,
            'escalation_authorisation',
        );

        $rendered = $renderer->render($template, [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordContact->name ?: 'your landlord',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'issue_description' => $case->description,
            'response_days' => (int) Setting::get('escalation.interval_days', 14),
            'notice_number' => $withheldNotice,
            'last_reply_date' => $this->lastLandlordReplyDate($case),
            'magic_link' => $magicLink,
        ]);

        Mail::to($case->tenant->email)->queue(new AutoEscalationTenantNotice(
            renderedSubject: $rendered['subject'],
            renderedBody: $rendered['body'],
        ));
    }

    /**
     * Active-row idiom — render an active tenant_notification template
     * and queue. Skip silently if no active row.
     */
    private function dispatchTenantNotification(
        RepairCase $case,
        string $code,
        string $purpose,
        ?int $silenceDaysOrNoticeNumber,
        LetterTemplateRenderer $renderer,
        MagicLinkGenerator $magicLinkGenerator,
    ): void {
        $template = LetterTemplate::query()
            ->where('type', 'tenant_notification')
            ->where('code', $code)
            ->where('active', true)
            ->first();
        if ($template === null) {
            return;
        }

        $case->loadMissing(['tenant', 'property', 'landlordContact']);

        $magicLink = $magicLinkGenerator->mintUrl(
            $case->tenant,
            $case,
            $purpose,
        );

        $rendered = $renderer->render($template, [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordContact->name ?: 'your landlord',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'issue_description' => $case->description,
            'response_days' => $silenceDaysOrNoticeNumber
                ?? (int) Setting::get('escalation.interval_days', 14),
            'notice_number' => $case->current_stage,
            'magic_link' => $magicLink,
        ]);

        Mail::to($case->tenant->email)->queue(new AutoEscalationTenantNotice(
            renderedSubject: $rendered['subject'],
            renderedBody: $rendered['body'],
        ));
    }

    private function propertyAddress(RepairCase $case): string
    {
        $p = $case->property;

        return implode(', ', array_filter([
            $p->address_line1,
            $p->address_line2,
            $p->city,
            $p->postcode,
        ]));
    }

    /**
     * D15 — the date the LANDLORD last replied, for the authorise-nudge
     * {{last_reply_date}}. Deliberately the most recent INBOUND LANDLORD
     * message, NOT the latest message on the case: in the thank-you path
     * the latest message is the tenant's own outbound reply, and the line
     * must show when the landlord last engaged. Ordered by received_at
     * (the inbound timestamp), id as tiebreak. Empty string if none —
     * the renderer collapses a missing whitelist value to '' rather than
     * leaving a literal token. Format matches the app's view convention
     * (d M Y); no date renders in any sibling template to copy from.
     */
    private function lastLandlordReplyDate(RepairCase $case): string
    {
        $lastReply = $case->messages()
            ->where('direction', MessageDirection::Inbound)
            ->where('sender_role', SenderRole::Landlord)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();

        return $lastReply?->received_at?->format('d M Y') ?? '';
    }

    private function resolveNow(): ?CarbonInterface
    {
        $pretend = $this->option('pretend-today');

        if ($pretend === null) {
            return Carbon::now();
        }

        if (! app()->environment(['local', 'staging', 'preprod'])) {
            $this->error('--pretend-today is restricted to the local, staging, and preprod environments.');

            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $pretend);
            if ($date === false) {
                throw new InvalidArgumentException('Carbon::createFromFormat returned false');
            }
        } catch (\Exception) {
            $this->error("--pretend-today must be a YYYY-MM-DD date; got: {$pretend}");

            return null;
        }

        return $date->startOfDay()->addHours(9);
    }

    private function writeShadowRow(
        int $caseId,
        SweepVerdict $verdict,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
        bool $executed,
    ): int {
        return SilenceShadowLog::create([
            'case_id' => $caseId,
            'swept_at' => $now,
            'ball_with' => $verdict->ballWith?->value,
            'silence_days' => $verdict->silenceDays,
            'intended_action' => $verdict->intendedAction->value,
            'executed' => $executed,
            'intended_letter_template_id' => $verdict->intendedLetterTemplate?->id,
            'escalation_counter_value' => $verdict->escalationCounterValue,
            'nudge_number' => $verdict->nudgeNumber,
            'reasoning' => $verdict->reasoning,
            'is_pretend' => $isPretend,
            'pretend_today' => $pretendToday,
        ])->id;
    }

    private function writeExceptionShadowRow(
        RepairCase $case,
        Throwable $e,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): int {
        $exClass = get_class($e);
        $exMessage = $e->getMessage();
        $reasoning = "exception during sweep: {$exClass}: {$exMessage}";
        if (strlen($reasoning) > 255) {
            $reasoning = substr($reasoning, 0, 252).'...';
        }

        return SilenceShadowLog::create([
            'case_id' => $case->id,
            'swept_at' => $now,
            'ball_with' => null,
            'silence_days' => null,
            'intended_action' => 'no_action',
            'executed' => false,
            'intended_letter_template_id' => null,
            'escalation_counter_value' => null,
            'nudge_number' => null,
            'reasoning' => $reasoning,
            'is_pretend' => $isPretend,
            'pretend_today' => $pretendToday,
        ])->id;
    }

    /**
     * Daily cleanup of orphaned cases/preview/{user_id}/{random}/
     * folders left behind when a tenant starts the create-case
     * preview flow (D13) but never confirms. Folders older than
     * 24 hours get removed. Live runs only; pretend never touches
     * disk.
     */
    private function cleanupPreviewPhotos(CarbonInterface $now): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('cases/preview')) {
            return;
        }

        $cutoff = $now->copy()->subDay();

        foreach ($disk->directories('cases/preview') as $userDir) {
            foreach ($disk->directories($userDir) as $previewDir) {
                $modifiedAt = Carbon::createFromTimestamp($disk->lastModified($previewDir));
                if ($modifiedAt->lessThan($cutoff)) {
                    $disk->deleteDirectory($previewDir);
                }
            }
        }
    }
}
