<?php

use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Lease pages held against a property — see
 * docs/cc-brief-property-details.md.
 *
 * The invariant these pin: A DOCUMENT HERE NEVER LEAVES THE PLATFORM.
 * Not attached to a case, not sent to a landlord, not on a letter. It is
 * held so a human can read the lease to identify the landlord's formal
 * service address.
 */
function leaseOwner(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $property = Property::factory()->create(['registered_by_user_id' => $user->id]);

    return [$user, $property];
}

it('stores an uploaded page against the property', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('page1.jpg')],
    ])->assertRedirect(route('properties.edit', $property));

    $document = PropertyDocument::sole();

    expect($document->property_id)->toBe($property->id)
        ->and($document->uploaded_by_user_id)->toBe($user->id)
        ->and($document->page_number)->toBe(1)
        ->and($document->original_filename)->toBe('page1.jpg')
        ->and($document->disk)->toBe('local');

    Storage::disk('local')->assertExists($document->path);
});

// The stored name must not be the tenant's own filename: an original
// name can carry a person's name, and a path is a thing that leaks.
it('does not use the tenant filename as the stored path', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('jane-smith-tenancy.jpg')],
    ]);

    expect(PropertyDocument::sole()->path)->not->toContain('jane-smith');
});

it('numbers pages in upload order and keeps them in order', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ],
    ]);

    expect($property->fresh()->documents->pluck('page_number')->all())->toBe([1, 2]);
});

// The #72 shape: a later upload must not rewrite what the tenant kept.
it('adds a later page without renumbering the existing ones', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]);

    $firstPaths = $property->fresh()->documents->pluck('path')->all();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('c.jpg')],
    ]);

    $after = $property->fresh()->documents;

    expect($after->pluck('page_number')->all())->toBe([1, 2, 3])
        ->and($after->take(2)->pluck('path')->all())->toBe($firstPaths);
});

it('refuses a file type that is neither an image nor a PDF', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->create('lease.exe', 10)],
    ])->assertSessionHasErrors('lease_documents.0');

    expect(PropertyDocument::count())->toBe(0);
});

it('accepts a PDF, not only photographs', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->create('lease.pdf', 10, 'application/pdf')],
    ]);

    expect(PropertyDocument::count())->toBe(1);
});

it('serves a page to its owner', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('page1.jpg')],
    ]);

    $document = PropertyDocument::sole();

    $this->actingAs($user)
        ->get(route('properties.documents.show', [$property, $document]))
        ->assertOk();
});

// A lease carries the tenant's name, the rent, and often third parties.
// Another signed-in user must get nowhere near it.
it('refuses a page to a different signed-in user', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();
    $stranger = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('page1.jpg')],
    ]);

    $document = PropertyDocument::sole();

    $this->actingAs($stranger)
        ->get(route('properties.documents.show', [$property, $document]))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->delete(route('properties.documents.destroy', [$property, $document]))
        ->assertForbidden();

    expect(PropertyDocument::count())->toBe(1);
});

// The row is built directly rather than through an authenticated upload:
// actingAs() signs the user in for the REST of the test, so a later
// request in the same test is never actually a guest. Worth stating,
// because the first draft of this test passed for that reason and proved
// nothing.
it('refuses a page to a guest', function () {
    Storage::fake('local');
    [, $property] = leaseOwner();

    $document = PropertyDocument::create([
        'property_id' => $property->id,
        'uploaded_by_user_id' => $property->registered_by_user_id,
        'kind' => 'lease',
        'disk' => 'local',
        'path' => 'properties/'.$property->id.'/documents/example.jpg',
        'original_filename' => 'page1.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
        'page_number' => 1,
        'uploaded_at' => now(),
    ]);

    $this->get(route('properties.documents.show', [$property, $document]))
        ->assertRedirect('/login');

    $this->delete(route('properties.documents.destroy', [$property, $document]))
        ->assertRedirect('/login');

    expect(PropertyDocument::count())->toBe(1);
});

it('removes the file from disk as well as the row', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('page1.jpg')],
    ]);

    $document = PropertyDocument::sole();
    $path = $document->path;

    $this->actingAs($user)
        ->delete(route('properties.documents.destroy', [$property, $document]))
        ->assertRedirect(route('properties.edit', $property));

    expect(PropertyDocument::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

// ───────────────────────────────────────────────────────────────────
// THE INVARIANT, MADE EXECUTABLE.
//
// A property document must not surface anywhere on the case side. This
// is the assertion that stops "attach the lease to the letter" from
// looking like a one-line change in six months' time.
// ───────────────────────────────────────────────────────────────────
it('never exposes a lease page on the tenant case page', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('page1.jpg')],
    ]);

    $document = PropertyDocument::sole();

    $case = RepairCase::factory()->create([
        'tenant_user_id' => $user->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertDontSee($document->path)
        ->assertDontSee($document->original_filename)
        ->assertDontSee(route('properties.documents.show', [$property, $document]), false);
});

it('keeps lease pages out of the case message attachment table', function () {
    Storage::fake('local');
    [$user, $property] = leaseOwner();

    $this->actingAs($user)->post("/properties/{$property->id}/documents", [
        'lease_documents' => [UploadedFile::fake()->image('page1.jpg')],
    ]);

    RepairCase::factory()->create([
        'tenant_user_id' => $user->id,
        'property_id' => $property->id,
    ]);

    // Different tables, and nothing bridges them. If a future change ever
    // copies a property document into an outbound attachment, this fails.
    expect(\App\Models\MessageAttachment::whereIn('path', PropertyDocument::pluck('path'))->count())
        ->toBe(0);
});
