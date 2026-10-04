<?php

namespace App\Http\Controllers;

use App\Actions\StorePropertyDocuments;
use App\Models\Property;
use App\Models\PropertyDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lease pages belonging to a property.
 *
 * EVERY ACTION HERE AUTHORISES AGAINST THE PROPERTY, and `show` serves
 * the file through PHP rather than handing out a URL. That is the whole
 * security model for these documents and it is deliberate: a lease
 * carries the tenant's name, the rent, and often third parties who never
 * agreed to anything. On a public disk it would be readable by anyone
 * who guessed a filename.
 *
 * See App\Models\PropertyDocument for the invariant these serve — a
 * document here never leaves the platform.
 */
class PropertyDocumentController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Property $property, StorePropertyDocuments $storer): RedirectResponse
    {
        $this->authorize('update', $property);

        $request->validate([
            'lease_documents' => ['array', 'max:'.StorePropertyDocuments::MAX_PAGES],
            'lease_documents.*' => StorePropertyDocuments::fileRules(),
        ], [
            'lease_documents.*.mimes' => 'Pages must be photographs (JPG, PNG, WEBP or HEIC) or a PDF.',
            'lease_documents.*.max' => 'Each page must be 4 MB or smaller.',
        ]);

        $stored = $storer->handle($property, $request->user(), $request->file('lease_documents', []));

        return redirect()
            ->route('properties.edit', $property)
            ->with('success', $stored === 1
                ? 'Page added to your lease agreement.'
                : "{$stored} pages added to your lease agreement.");
    }

    /**
     * Stream the file. Never a redirect to storage, never a signed URL
     * that outlives the request.
     */
    public function show(Property $property, PropertyDocument $document): StreamedResponse
    {
        $this->authorize('view', $property);

        abort_unless($document->property_id === $property->id, 404);

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_filename, [
            // Inline so a tenant can glance at a page without downloading
            // it; nosniff so a mislabelled upload cannot be coaxed into
            // executing as something else.
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Property $property, PropertyDocument $document): RedirectResponse
    {
        $this->authorize('update', $property);

        abort_unless($document->property_id === $property->id, 404);

        // Remove the file as well as the row. A lease the tenant has
        // deleted should not survive on disk: they asked for it to be
        // gone, and it is their document.
        Storage::disk($document->disk)->delete($document->path);

        $document->delete();

        return redirect()
            ->route('properties.edit', $property)
            ->with('success', 'Page removed.');
    }
}
