<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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

        Property::create([
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'postcode' => $this->normalisePostcode($validated['postcode']),
            'registered_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('properties.index')
            ->with('success', 'Property registered.');
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
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postcode' => ['required', 'string', 'max:20', 'regex:'.self::POSTCODE_PATTERN],
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
