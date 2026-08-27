<?php

namespace App\Http\Controllers;

use App\Enums\LandlordContactRole;
use App\Models\Property;
use App\Models\PropertyLandlordContact;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Correcting a property's landlord contact — snag #24.
 *
 * The whole of the fix is here: a correction inserts a NEW version and
 * supersedes the old one, so every subsequent letter on every open case
 * at this property goes to the corrected address. No abandon-and-
 * re-raise, no lost case reference.
 *
 * Three things it deliberately does NOT do:
 *
 * 1. It does not touch case_messages. Letters already sent keep their
 *    frozen to_address_raw, subject and body_raw. A correction changes
 *    where the NEXT letter goes and nothing else.
 *
 * 2. It does not send. SendCaseNotice sets stage_at_send to
 *    current_stage + 1 on any non-first send, so auto-sending on
 *    correction would escalate the case as the price of fixing a typo —
 *    a straight D3 violation (the counter increments and never resets).
 *    The next scheduled send binds a token to the new address.
 *
 * 3. It does not revoke the in-flight reply token. That token is
 *    evidentially harmless, and the quarantine sender-match already
 *    guards the superseded address: a reply from the old email now
 *    quarantines rather than being accepted as the landlord's.
 *
 * What it DOES do is write a landlord_contact_corrected event on every
 * open case at the property, so the record shows the correction rather
 * than the address silently changing underneath it.
 */
class PropertyLandlordContactController extends Controller
{
    use AuthorizesRequests;

    public function edit(Property $property): View
    {
        $this->authorize('update', $property);

        return view('properties.contact', [
            'property' => $property,
            'contact' => $property->currentLandlordContact,
            'history' => $property->landlordContacts()->with(['createdBy', 'supersededBy'])->get(),
            'roles' => LandlordContactRole::cases(),
        ]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(LandlordContactRole::class)],
            'organisation_name' => ['nullable', 'string', 'max:255'],
            // Landlord's own postal address. Store-and-display only — it
            // never reaches a letter template.
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            // Deliberately looser than the property's own postcode rule: a
            // managing agent can sit at a non-UK address, and rejecting one
            // would block the tenant from serving notice at all.
            'postcode' => ['nullable', 'string', 'max:20'],
        ]);

        $previous = $property->currentLandlordContact;
        $email = strtolower(trim($validated['email']));

        if ($previous && $this->isUnchanged($previous, $validated, $email)) {
            return redirect()
                ->route('properties.contact.edit', $property)
                ->with('success', 'No changes to save — the landlord details are already as entered.');
        }

        $now = now();

        DB::transaction(function () use ($property, $validated, $email, $previous, $now, $request) {
            $property->setLandlordContact([
                'email' => $email,
                'name' => $validated['name'] ?? null,
                'role' => $validated['role'],
                'organisation_name' => $validated['organisation_name'] ?? null,
                'address_line1' => $validated['address_line1'] ?? null,
                'address_line2' => $validated['address_line2'] ?? null,
                'city' => $validated['city'] ?? null,
                'postcode' => $this->normalisePostcode($validated['postcode'] ?? null),
            ], $now, $request->user()->id);

            if ($previous) {
                $this->recordCorrectionOnOpenCases($property, $previous->email, $email, $now, $request->user()->id);
            }
        });

        return redirect()
            ->route('properties.contact.edit', $property)
            ->with('success', $previous
                ? 'Landlord details corrected. Future letters on this property will go to the new address.'
                : 'Landlord details saved.');
    }

    /**
     * A resubmit with nothing altered should not manufacture a version.
     * The history is meant to read as a list of decisions, and an
     * accidental double-submit is not one.
     *
     * @param  array<string, mixed>  $validated
     */
    private function isUnchanged(PropertyLandlordContact $previous, array $validated, string $email): bool
    {
        return $previous->email === $email
            && $previous->name === ($validated['name'] ?? null)
            && $previous->role->value === $validated['role']
            && $previous->organisation_name === ($validated['organisation_name'] ?? null)
            && $previous->address_line1 === ($validated['address_line1'] ?? null)
            && $previous->address_line2 === ($validated['address_line2'] ?? null)
            && $previous->city === ($validated['city'] ?? null)
            && $previous->postcode === $this->normalisePostcode($validated['postcode'] ?? null);
    }

    /**
     * Write the correction onto every case still running at this
     * property, so a reader of the case record sees that the address was
     * changed, by whom and when — rather than finding letter 3 addressed
     * somewhere letter 2 was not.
     *
     * Closed cases are skipped: they will never send again, so nothing
     * about them changed.
     */
    private function recordCorrectionOnOpenCases(
        Property $property,
        string $from,
        string $to,
        CarbonInterface $at,
        int $actorUserId,
    ): void {
        $cases = $property->cases()->get()->reject(fn ($case) => $case->isClosed());

        foreach ($cases as $case) {
            $case->events()->create([
                'event_type' => 'landlord_contact_corrected',
                'actor_user_id' => $actorUserId,
                'actor_label' => 'tenant',
                'occurred_at' => $at,
                'meta' => ['from' => $from, 'to' => $to],
            ]);
        }
    }

    private function normalisePostcode(?string $postcode): ?string
    {
        if (! filled($postcode)) {
            return null;
        }

        return strtoupper(trim($postcode));
    }
}
