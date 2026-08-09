<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\LandlordContact;
use App\Models\MessageAttachment;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    RepairCategory::factory()->create([
        'key' => 'damp_mould',
        'label' => 'Damp and mould',
        'active' => true,
        'requires_description' => false,
    ]);
});

function tenantWithProperty(): array
{
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    return [$tenant, $property];
}

function validStorePayload(int $propertyId, array $overrides = []): array
{
    return array_merge([
        'property_id' => $propertyId,
        'category_key' => 'damp_mould',
        'severity' => 'routine',
        'description' => 'Black mould has spread along the bedroom wall over the last two weeks.',
        'landlord_email' => 'landlord@example.com',
        'landlord_name' => 'Jane Doe',
        'landlord_role' => 'landlord',
    ], $overrides);
}

/**
 * Phase 3 D13 — create flow is two-step: store→preview→confirm.
 * Most existing tests verify the end result (a created case + queued
 * notice), so the helper drives both POSTs.
 */
function submitAndConfirm(User $tenant, array $payload): \Illuminate\Testing\TestResponse
{
    test()->actingAs($tenant)->post('/cases', $payload);

    return test()->actingAs($tenant)->post('/cases/preview/confirm');
}

it('shows the create form with the tenant\'s properties and active categories', function () {
    [$tenant, $property] = tenantWithProperty();

    $response = $this->actingAs($tenant)->get('/cases/create');

    $response->assertOk();
    $response->assertSee($property->address_line1);
    $response->assertSee('Damp and mould');
});

it('does not show another tenant\'s properties on the create form', function () {
    $tenant = User::factory()->create();
    $otherTenant = User::factory()->create();
    Property::factory()->create([
        'registered_by_user_id' => $otherTenant->id,
        'address_line1' => '999 Hidden Lane',
    ]);

    $response = $this->actingAs($tenant)->get('/cases/create');

    $response->assertOk();
    $response->assertDontSee('999 Hidden Lane');
});

it('POST /cases stages the payload and redirects to the preview, NOT the case', function () {
    [$tenant, $property] = tenantWithProperty();

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload($property->id));

    $response->assertRedirect('/cases/preview');
    expect(RepairCase::count())->toBe(0);
});

it('Edit round-trip — GET /cases/create after staging re-fills the form from the staged payload (D0.8)', function () {
    [$tenant, $property] = tenantWithProperty();

    // Stage a draft (POST /cases) including a photo, then click "Edit"
    // (a plain GET back to the create form).
    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['description' => 'Persistent damp behind the kitchen units.']
    ) + ['photos' => [UploadedFile::fake()->image('damp.jpg')]]);

    // ?resume=1 is what the preview's Edit link carries. Without it the
    // create form treats the visit as a fresh case and clears the draft —
    // see the abandon-then-start-new test below (snag #44).
    $response = $this->actingAs($tenant)->get('/cases/create?resume=1');

    $response->assertOk();
    // old() repopulates the text inputs from the staged payload.
    $response->assertSee('Persistent damp behind the kitchen units.');
    $response->assertSee('landlord@example.com');
    // The staged photo can't re-seed a file input, but it survives in the
    // session payload. It is now NAMED on the form rather than counted by a
    // cue — "your photo is saved" told the tenant a number and, until #46
    // was fixed, was not even true. Naming the file is the stronger claim:
    // the tenant can see WHICH evidence is attached.
    $response->assertSee('damp.jpg');
    $response->assertSee('attached');
    // And the keep flag rides along so a resubmit carries it forward.
    $response->assertSee('name="keep_staged_photos"', false);
    $response->assertSee('value="1"', false);
});

it('Edit round-trip does not leak another tenant\'s staged draft', function () {
    [$tenant, $property] = tenantWithProperty();
    [$otherTenant] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['description' => 'Only this tenant should see this draft.']
    ));

    // A different tenant hitting the create form gets a blank form — the
    // payload is gated on user_id.
    $response = $this->actingAs($otherTenant)->get('/cases/create');

    $response->assertOk();
    $response->assertDontSee('Only this tenant should see this draft.');
});

it('preview confirm creates the case, mints a token, queues the notice, and transitions to awaiting_landlord', function () {
    [$tenant, $property] = tenantWithProperty();

    $response = submitAndConfirm($tenant, validStorePayload($property->id));

    $response->assertRedirectContains('/cases/');

    $case = RepairCase::firstOrFail();
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->tenant_user_id)->toBe($tenant->id);
    expect($case->property_id)->toBe($property->id);
    expect($case->current_stage)->toBe(1);
    expect($case->silence_clock_started_at)->not->toBeNull();
    expect($case->ball_with)->toBe('landlord');
    expect($case->replyTokens()->whereNull('superseded_at')->count())->toBe(1);

    Mail::assertQueued(CaseNotice::class);
});

it('writes the description onto cases.description (D9) — frozen at creation', function () {
    [$tenant, $property] = tenantWithProperty();

    submitAndConfirm($tenant, validStorePayload(
        $property->id,
        ['description' => 'The boiler has been silent for nine days.']
    ));

    $case = RepairCase::firstOrFail();
    expect($case->description)->toBe('The boiler has been silent for nine days.');
});

it('the outbound message body carries the description via the D9 header block', function () {
    [$tenant, $property] = tenantWithProperty();

    submitAndConfirm($tenant, validStorePayload(
        $property->id,
        ['description' => 'The boiler has been silent for nine days.']
    ));

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    expect($message->body_raw)->toContain('The boiler has been silent for nine days.');
});

it('reuses an existing landlord_contact when the email matches', function () {
    [$tenant, $property] = tenantWithProperty();
    $existing = LandlordContact::factory()->create(['email' => 'shared@example.com']);

    submitAndConfirm($tenant, validStorePayload(
        $property->id,
        ['landlord_email' => 'SHARED@example.com']
    ));

    expect(LandlordContact::where('email', 'shared@example.com')->count())->toBe(1);
    expect(RepairCase::firstOrFail()->landlord_contact_id)->toBe($existing->id);
});

it('creates a new landlord_contact when the email is unknown', function () {
    [$tenant, $property] = tenantWithProperty();

    submitAndConfirm($tenant, validStorePayload(
        $property->id,
        ['landlord_email' => 'fresh@example.com', 'landlord_name' => 'Sam Owner']
    ));

    $contact = LandlordContact::where('email', 'fresh@example.com')->firstOrFail();
    expect($contact->name)->toBe('Sam Owner');
    expect($contact->invited_by_user_id)->toBe($tenant->id);
});

it('rejects a property_id that belongs to another user', function () {
    $tenant = User::factory()->create();
    $otherTenant = User::factory()->create();
    $foreignProperty = Property::factory()->create(['registered_by_user_id' => $otherTenant->id]);

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload($foreignProperty->id));

    $response->assertSessionHasErrors('property_id');
    expect(RepairCase::count())->toBe(0);
});

it('uploads photos and creates message_attachments rows on the outbound message', function () {
    [$tenant, $property] = tenantWithProperty();

    // The shipped ceiling is 1 (attachments.first_notice_max). This test is
    // about the multi-attachment path, so raise it explicitly rather than
    // weakening the assertion to a single file.
    allowPhotoCeiling(3);

    $files = [
        UploadedFile::fake()->image('mould-1.jpg'),
        UploadedFile::fake()->image('mould-2.png'),
    ];

    submitAndConfirm($tenant, validStorePayload($property->id) + ['photos' => $files]);

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    $attachments = MessageAttachment::where('case_message_id', $message->id)->get();
    expect($attachments)->toHaveCount(2);

    foreach ($attachments as $attachment) {
        expect($attachment->direction)->toBe(MessageDirection::Outbound);
        Storage::disk('local')->assertExists($attachment->path);
    }
});

it('creates a case successfully when no photos are uploaded', function () {
    [$tenant, $property] = tenantWithProperty();

    $response = submitAndConfirm($tenant, validStorePayload($property->id));

    $response->assertRedirectContains('/cases/');
    expect(MessageAttachment::count())->toBe(0);
    expect(RepairCase::count())->toBe(1);
});

it('rejects a payload with missing required fields', function () {
    [$tenant] = tenantWithProperty();

    $response = $this->actingAs($tenant)->post('/cases', []);

    $response->assertSessionHasErrors([
        'property_id',
        'category_key',
        'severity',
        'description',
        'landlord_email',
        'landlord_role',
    ]);
    expect(RepairCase::count())->toBe(0);
});

it('rejects a missing description (now always required per D9)', function () {
    [$tenant, $property] = tenantWithProperty();

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['description' => '']
    ));

    $response->assertSessionHasErrors('description');
    expect(RepairCase::count())->toBe(0);
});

it('rejects a category that is inactive', function () {
    RepairCategory::factory()->create([
        'key' => 'archived',
        'active' => false,
    ]);
    [$tenant, $property] = tenantWithProperty();

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['category_key' => 'archived']
    ));

    $response->assertSessionHasErrors('category_key');
    expect(RepairCase::count())->toBe(0);
});

it('redirects guests away from /cases/create', function () {
    $response = $this->get('/cases/create');

    $response->assertRedirect('/login');
});

/*
|--------------------------------------------------------------------------
| Attachment policy — docs/attachment-policy-design.md
|--------------------------------------------------------------------------
*/

it('refuses more photos than the ceiling allows', function () {
    [$tenant, $property] = tenantWithProperty();

    allowPhotoCeiling(1);

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ],
    ]);

    $response->assertSessionHasErrors('photos');
    expect(RepairCase::count())->toBe(0);
});

it('honours a raised ceiling', function () {
    [$tenant, $property] = tenantWithProperty();

    allowPhotoCeiling(3);

    submitAndConfirm($tenant, validStorePayload($property->id) + [
        'photos' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
            UploadedFile::fake()->image('three.jpg'),
        ],
    ]);

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    expect(MessageAttachment::where('case_message_id', $message->id)->count())->toBe(3);
});

it('hides the photo input and explains why when the ceiling is 0', function () {
    [$tenant] = tenantWithProperty();

    allowPhotoCeiling(0);

    $response = $this->actingAs($tenant)->get('/cases/create');

    $response->assertOk();
    $response->assertSee('Photos can\'t be attached to this letter at the moment', false);
    // The input itself is gone, not merely hidden.
    $response->assertDontSee('name="photos[]"', false);
});

it('R2 — a ceiling lowered AFTER staging never drops a photo the tenant already chose', function () {
    [$tenant, $property] = tenantWithProperty();

    // Tenant stages a photo under a ceiling that permits it.
    allowPhotoCeiling(1);
    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('damp.jpg')],
    ]);

    // Admin switches attachments off entirely between the two clicks.
    allowPhotoCeiling(0);

    $this->actingAs($tenant)->post('/cases/preview/confirm');

    // The letter still carries what the tenant chose. A ceiling change must
    // never edit a tenant's letter behind them — that is the silent evidence
    // loss this whole design exists to eliminate.
    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    expect(MessageAttachment::where('case_message_id', $message->id)->count())->toBe(1);
});

it('names the file and states the limit in plain words when a photo is too large', function () {
    [$tenant, $property] = tenantWithProperty();

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->create('kitchen-damp.jpg', 5000, 'image/jpeg')],
    ]);

    $errors = session('errors')->getBag('default')->all();
    $message = implode(' ', $errors);

    // The tenant's own filename, not an array index.
    expect($message)->toContain('kitchen-damp.jpg');
    expect($message)->not->toContain('photos.0');
    // A unit people think in, and the real limit.
    expect($message)->toContain('4 MB');
    expect($message)->not->toContain('kilobytes');
});

it('lists the staged photos on the preview, with sizes', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('bedroom-mould.jpg')],
    ]);

    $response = $this->actingAs($tenant)->get('/cases/preview');

    $response->assertOk();
    $response->assertSee('bedroom-mould.jpg');
    $response->assertSee('photo will be attached');
});

it('says explicitly on the preview when no photos are attached', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id));

    $response = $this->actingAs($tenant)->get('/cases/preview');

    $response->assertOk();
    // Absence must be stated, never inferred from a blank region — a tenant
    // whose upload was rejected sees the same screen otherwise.
    $response->assertSee('No photos attached.');
});

it('#44 — an abandoned draft does not tell a NEW case that photos are saved', function () {
    [$tenant, $property] = tenantWithProperty();

    // Stage a draft, reach the preview, then walk away.
    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('old-draft.jpg')],
    ]);

    // Start a fresh case: no ?resume=1, so this is not a return via Edit.
    $response = $this->actingAs($tenant)->get('/cases/create');

    $response->assertOk();
    // The cue would otherwise read "Your photo is saved — you don't need to
    // re-attach it", talking the tenant out of attaching evidence to a case
    // that has none.
    $response->assertDontSee('you don\'t need to re-attach', false);

    // And the stale draft is gone rather than lying in wait.
    expect(session('cases.preview.payload'))->toBeNull();
});

it('#46 — Edit, change a word, resubmit: the staged photos survive', function () {
    [$tenant, $property] = tenantWithProperty();

    // Stage a draft WITH a photo.
    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('damp.jpg')],
    ]);

    // Edit, reword the description, resubmit. A file input cannot be
    // re-seeded by the browser, so the second POST legitimately carries no
    // files — exactly what a tenant does when the form tells them "your
    // photo is saved, you don't need to re-attach it".
    $this->actingAs($tenant)->get('/cases/create?resume=1');
    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id, [
        'description' => 'Reworded: black mould along the bedroom wall, worsening.',
    ]));

    $this->actingAs($tenant)->post('/cases/preview/confirm');

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    expect(MessageAttachment::where('case_message_id', $message->id)->count())->toBe(1);
});

it('#46 — an explicit Remove does clear the staged photos', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('damp.jpg')],
    ]);

    // keep_staged_photos=0 is what the form's script sets when the tenant
    // clicks Remove. Only an explicit 0 drops them — absent means keep.
    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'keep_staged_photos' => 0,
    ]);

    $this->actingAs($tenant)->post('/cases/preview/confirm');

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    expect(MessageAttachment::where('case_message_id', $message->id)->count())->toBe(0);
});

it('#46 — newly chosen photos REPLACE the staged set rather than adding to it', function () {
    [$tenant, $property] = tenantWithProperty();

    allowPhotoCeiling(3);

    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('first.jpg')],
    ]);

    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('second.jpg')],
    ]);

    $this->actingAs($tenant)->post('/cases/preview/confirm');

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    $attachments = MessageAttachment::where('case_message_id', $message->id)->get();

    expect($attachments)->toHaveCount(1);
    expect($attachments->first()->original_filename)->toBe('second.jpg');
});

it('#45 — drops an attachment whose staged file has been swept, rather than recording one that is not there', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => [UploadedFile::fake()->image('vanished.jpg')],
    ]);

    // Simulate SilenceSweep::cleanupPreviewPhotos having removed the staged
    // folder overnight, before the tenant came back and confirmed.
    $payload = session('cases.preview.payload');
    Storage::disk('local')->delete($payload['photos'][0]['path']);

    $this->actingAs($tenant)->post('/cases/preview/confirm');

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();

    // No row pointing at a file that does not exist: CaseNotice would throw
    // on it inside a queued job, and the case page would list evidence the
    // tenant does not have.
    expect(MessageAttachment::where('case_message_id', $message->id)->count())->toBe(0);
});

it('redirects guests away from POST /cases', function () {
    $response = $this->post('/cases', []);

    $response->assertRedirect('/login');
});
