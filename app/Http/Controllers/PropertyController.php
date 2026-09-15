<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Rules\PostcodeIsReal;
use App\Services\PostcodeLookup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tenant-side registry for properties the tenant rents. The case
 * creation flow (CaseController) uses the property table as the
 * source of truth for which addresses a tenant is associated with;
 * Phase 6.5 closes the gap where no UI existed to populate it.
 *
 * Ownership is enforced via PropertyPolicy on view/update. Delete is
 * deferred — the FK from cases.property_id is RESTRICT, so the only
 * properties safe to delete are those with no cases, which we can
 * leave as harmless orphans for v1.
 */
class PropertyController extends Controller
{
    use AuthorizesRequests;

    private const POSTCODE_PATTERN = '/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i';

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Property::class);

        $properties = Property::query()
            ->where('registered_by_user_id', $request->user()->id)
            ->orderBy('address_line1')
            ->get();

        return view('properties.index', ['properties' => $properties]);
    }

    public function create(): View
    {
        $this->authorize('create', Property::class);

        return view('properties.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

        $validated = $this->validatePayload($request);

        $property = Property::create([
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'postcode' => $this->normalisePostcode($validated['postcode']),
            'registered_by_user_id' => $request->user()->id,
        ]);

        // Snag #66. The landlord belongs to the PROPERTY, so it is asked for
        // with the property — not buried in the create-case form, where what
        // the user types silently becomes this property's permanent landlord
        // without looking like a property decision.
        //
        // No first-property branch. It used to send a first property to
        // raise-a-case and later ones to the list; ruled 15 Sep that a second
        // property is rare enough not to warrant its own path, which removes
        // the branch rather than adding a third case to it. The landlord page
        // itself decides what comes next: newly set -> on to raise-a-case,
        // corrected later -> stay put.
        return redirect()
            ->route('properties.contact.edit', $property)
            ->with('success', 'Property registered. Now add your landlord or agent, so we know who to write to.');
    }

    public function edit(Property $property): View
    {
        $this->authorize('update', $property);

        return view('properties.edit', ['property' => $property]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $validated = $this->validatePayload($request);

        $property->update([
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'postcode' => $this->normalisePostcode($validated['postcode']),
        ]);

        return redirect()
            ->route('properties.index')
            ->with('success', 'Property updated.');
    }

    /**
     * #51. The form's postcode lookup.
     *
     * Deliberately proxied through us rather than called from the
     * browser: the cache lives here, one shape of answer is returned
     * whatever postcodes.io does, and the tenant's browser never talks
     * to a third party directly.
     *
     * Returns UNKNOWN rather than an error on any failure. The form
     * treats UNKNOWN as "say nothing", so an outage is silent rather
     * than alarming.
     */
    public function lookupPostcode(Request $request, PostcodeLookup $lookup): JsonResponse
    {
        $postcode = (string) $request->query('postcode', '');

        if (trim($postcode) === '') {
            return response()->json([
                'status' => PostcodeLookup::UNKNOWN,
                'postcode' => null,
                'district' => null,
            ]);
        }

        return response()->json($lookup->lookup($postcode));
    }
    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            // #51: shape first, then existence. The existence check fails
            // OPEN — see the PostcodeIsReal rule.
            'postcode' => [
                'required',
                'string',
                'max:20',
                'regex:'.self::POSTCODE_PATTERN,
                app(PostcodeIsReal::class),
            ],
        ], [
            'postcode.regex' => 'Enter a valid UK postcode (for example, M1 1AA).',
        ]);
    }

    private function normalisePostcode(string $postcode): string
    {
        $stripped = strtoupper(preg_replace('/\s+/', '', $postcode));

        return preg_replace('/^(.+)(\d[A-Z]{2})$/', '$1 $2', $stripped);
    }
}
