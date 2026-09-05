<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use App\Services\LetterTemplateRenderer;
use App\Services\MagicLinkGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * #25 — writes a Mailgun delivery event into the case record, and reacts
 * to it where D17 says to.
 *
 * THE REACTION IS DELIBERATELY NARROW. A PERMANENT failure and a
 * COMPLAINT both stop the case at contact_failed and notify the tenant;
 * a temporary failure and a delivery are recorded and nothing else
 * happens. Which statuses may be stopped is D17.8, and the check is
 * TRANSITIONS itself — an event arriving at a case that has already
 * stopped is recorded and left alone, so a late bounce never reopens a
 * resolved case or rewrites why it ended.
 *
 * WHY case_events AND NOT COLUMNS ON case_messages
 * Ruled 24 Aug, superseding the original fix line. The frozen evidential
 * case_messages row is never mutated; the record is append-only, which
 * suits several events per message; and it needs no migration. The cost,
 * accepted at the time: case_events keys to case_id, so case_message_id
 * lives in JSON meta with no FK, and "was letter 3 delivered?" is a meta
 * lookup rather than a column read.
 *
 * THE COUNTER IS UNTOUCHED. case_events is not case_messages, so nothing
 * written here can perturb the escalation predicate (outbound system rows
 * with a non-null stage_at_send). The tenant notification is mail-only
 * for the same reason: persisting one would inflate the ladder as the
 * price of reporting a failure.
 *
 * Field shapes come from three real production sends captured 23 Aug
 * 2026 — docs/mailgun-delivery-event-payloads.md — not from Mailgun's
 * documentation.
 */
class RecordDeliveryEvent
{
    /**
     * D17.9. `delivery_failed` is fixed by the #25 ruling;
     * `delivery_confirmed` is its counterpart, named for what it means —
     * a mail server accepted the message, never that anyone read it.
     * `delivery_complained` is evidence of RECEIPT (D17.5's asymmetry).
     */
    private const EVENT_TYPES = [
        'failed' => 'delivery_failed',
        'delivered' => 'delivery_confirmed',
        'complained' => 'delivery_complained',
    ];

    /**
     * Template codes for the two stopping causes. They say different
     * things: a bounce means the address is wrong and can be corrected;
     * a complaint means the letter ARRIVED and was rejected, so there is
     * nothing to correct (D17.5 — complaints never fork).
     */
    private const NOTICE_TEMPLATES = [
        'failed' => 'contact_failed_bounce',
        'complained' => 'contact_failed_complaint',
    ];

    public function __construct(
        private LetterTemplateRenderer $renderer,
        private MagicLinkGenerator $magicLinks,
    ) {}

    /**
     * @param  array<string, mixed>  $eventData  the `event-data` object
     * @return string one of: recorded, duplicate, unmatched, unhandled
     */
    public function execute(array $eventData): string
    {
        $mailgunEventId = (string) ($eventData['id'] ?? '');
        $event = (string) ($eventData['event'] ?? '');

        // Anything outside the ruled set is accepted and logged rather
        // than recorded. In practice that is `opened`, `clicked` and
        // `unsubscribed`, none of which should ever arrive: we set no
        // tracking options and no unsubscribe headers on our sends. If one
        // does, the log line is the useful part — it means a Mailgun
        // setting changed underneath us.
        if (! array_key_exists($event, self::EVENT_TYPES)) {
            Log::info('Mailgun delivery event ignored: unhandled type', [
                'event' => $event,
                'mailgun_event_id' => $mailgunEventId,
            ]);

            return 'unhandled';
        }

        $message = $this->resolveMessage($eventData);

        if ($message === null) {
            // D17.9 — accept and log. Returning an error would make
            // Mailgun retry for hours against a payload that cannot match
            // on any attempt.
            Log::warning('Mailgun delivery event could not be matched to a case message', [
                'event' => $event,
                'mailgun_event_id' => $mailgunEventId,
                'case_message_id' => $eventData['user-variables']['case_message_id'] ?? null,
                'recipient' => $eventData['recipient'] ?? null,
            ]);

            return 'unmatched';
        }

        $case = $message->case;

        // D17.9 — Mailgun re-sends when unsure a webhook arrived. Scoped
        // to this case, so it is a small read rather than a table scan.
        if ($mailgunEventId !== '' && $case->events()
            ->where('meta->mailgun_event_id', $mailgunEventId)
            ->exists()) {
            return 'duplicate';
        }

        DB::transaction(function () use ($case, $message, $event, $eventData, $mailgunEventId) {
            $this->writeEvent($case, $message, $event, $eventData, $mailgunEventId);

            if (! $this->stops($event, $eventData)) {
                return;
            }

            // D17.8 — TRANSITIONS is the authority on which statuses may be
            // stopped. A late event at an already-stopped case falls out
            // here: recorded, but the case is left exactly as it was.
            if (! RepairCase::isTransitionAllowed($case->status, CaseStatus::ContactFailed)) {
                return;
            }

            // No event_type_override: the transition writes its own
            // `case_contact_failed`, distinct from the `delivery_failed` /
            // `delivery_complained` row above. Two separate true statements
            // rather than one duplicated — the Mailgun fact, and the case
            // stopping because of it. Per D17.1 the record extends.
            $case->transitionTo(CaseStatus::ContactFailed, [
                'actor_label' => 'system',
            ]);

            $this->notifyTenant($case, $event, $eventData);
        });

        return 'recorded';
    }

    /**
     * D17.2 — hard stops, soft is silent. D17.5 — a complaint is terminal
     * wherever it occurs. Branch on SEVERITY, never on the event name:
     * there is no `permanent_fail` event whatever the subscription UI
     * shows, and a parser keyed off that name would match nothing.
     *
     * @param  array<string, mixed>  $eventData
     */
    private function stops(string $event, array $eventData): bool
    {
        if ($event === 'complained') {
            return true;
        }

        return $event === 'failed'
            && ($eventData['severity'] ?? null) === 'permanent';
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function writeEvent(
        RepairCase $case,
        CaseMessage $message,
        string $event,
        array $eventData,
        string $mailgunEventId,
    ): void {
        $case->events()->create([
            'event_type' => self::EVENT_TYPES[$event],
            'actor_label' => 'system',
            'occurred_at' => now(),
            'meta' => [
                'case_message_id' => $message->id,
                'mailgun_event_id' => $mailgunEventId,
                // Branch on severity, NEVER on the event name: there is no
                // permanent_fail event, whatever the subscription UI shows.
                'severity' => $eventData['severity'] ?? null,
                // The discriminator that separates a real bounce
                // (`generic`) from a send Mailgun dropped without trying,
                // because the address is already suppressed
                // (`suppress-bounce`). Both arrive as failed/permanent.
                'reason' => $eventData['reason'] ?? null,
                'recipient' => $eventData['recipient'] ?? null,
                'mailgun_timestamp' => $this->timestamp($eventData),
            ],
        ]);
    }

    /**
     * Mail-only, and it writes NO case_messages row — the invariant the
     * whole silence model rests on.
     *
     * Active-row idiom, matching every other tenant notification: no
     * active template row means no send. Silence there is the designed
     * behaviour rather than a failure — the case is still stopped and the
     * event is still recorded.
     *
     * @param  array<string, mixed>  $eventData
     */
    private function notifyTenant(RepairCase $case, string $event, array $eventData): void
    {
        $template = LetterTemplate::query()
            ->where('type', 'tenant_notification')
            ->where('code', self::NOTICE_TEMPLATES[$event])
            ->where('active', true)
            ->first();

        if ($template === null) {
            return;
        }

        $case->loadMissing(['tenant', 'property']);

        $rendered = $this->renderer->render($template, [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordRecipient()?->name ?: 'your landlord',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'issue_description' => $case->description,
            'failed_address' => (string) ($eventData['recipient'] ?? ''),
            'magic_link' => $this->magicLinks->mintUrl($case->tenant, $case, 'contact_failed'),
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
     * Correlation is by our own custom variable, proven to survive the
     * round trip on all three captured sends. `recipient` is necessary
     * but not sufficient — one address can carry many cases.
     *
     * @param  array<string, mixed>  $eventData
     */
    private function resolveMessage(array $eventData): ?CaseMessage
    {
        $id = $eventData['user-variables']['case_message_id'] ?? null;

        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return CaseMessage::with('case')->find((int) $id);
    }

    /**
     * Mailgun sends Unix seconds with a fractional part.
     *
     * @param  array<string, mixed>  $eventData
     */
    private function timestamp(array $eventData): ?string
    {
        $ts = $eventData['timestamp'] ?? null;

        if (! is_numeric($ts)) {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $ts)->toDateTimeString();
    }
}
