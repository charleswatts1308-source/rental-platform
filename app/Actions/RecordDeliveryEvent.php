<?php

namespace App\Actions;

use App\Models\CaseMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * #25 step 6 — writes a Mailgun delivery event into the case record.
 *
 * RECORD ONLY. This action does not transition a case, notify anyone or
 * touch case_messages. Reacting to a failure is step 7 (D17.2, D17.8);
 * keeping the seam means the receiver can be deployed and observed
 * before it is allowed to change anything.
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
 * with a non-null stage_at_send).
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
     */
    private const EVENT_TYPES = [
        'failed' => 'delivery_failed',
        'delivered' => 'delivery_confirmed',
        // Ruled 5 Sep. A complaint is EVIDENCE OF RECEIPT — D17.5's
        // asymmetry: a bounce proves the letter went nowhere, a complaint
        // proves the opposite, that it arrived and was seen. Recorded for
        // the same reason D17.7 captures `delivered`: Mailgun's own log
        // expires in a day, and this is a signature-verifiable record that
        // outlives it. What a complaint DOES is step 7 (D17.5 makes it
        // terminal wherever it occurs, and it never forks).
        'complained' => 'delivery_complained',
    ];

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

        return 'recorded';
    }

    /**
     * Correlation is by our own custom variable, proven to survive the
     * round trip on all three captured sends. `recipient` is necessary
     * but not sufficient — one address can carry many cases.
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
