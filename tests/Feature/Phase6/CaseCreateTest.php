<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\MessageAttachment;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\User;
use App\Support\FileSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

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
function submitAndConfirm(User $tenant, array $payload): TestResponse
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

/*
 * These two replace the old landlord_contacts dedup tests. That table is
 * no longer read by anything and is dropped in the final commit, so
 * asserting on it would be asserting on nothing. The assertions here are
 * strictly stronger: they check the contact the case will actually be
 * SERVED on, which is what the old pair only stood in for.
 */
it('creates the property landlord contact as version 1 when the property has none', function () {
    [$tenant, $property] = tenantWithProperty();

    submitAndConfirm($tenant, validStorePayload(
        $property->id,
        ['landlord_email' => 'Fresh@Example.com', 'landlord_name' => 'Sam Owner']
    ));

    $contact = $property->fresh()->currentLandlordContact;

    expect($contact->email)->toBe('fresh@example.com')
        ->and($contact->name)->toBe('Sam Owner')
        ->and($contact->created_by_user_id)->toBe($tenant->id)
        ->and($contact->source->value)->toBe('entered')
        ->and(RepairCase::firstOrFail()->property_landlord_contact_id)->toBe($contact->id);
});

it('inherits the property existing contact and does NOT create a second version', function () {
    [$tenant, $property] = tenantWithProperty();
    $existing = $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    submitAndConfirm($tenant, validStorePayload(
        $property->id,
        ['landlord_email' => 'typed@example.com', 'landlord_name' => 'Typed Name']
    ));

    expect($property->fresh()->landlordContacts()->count())->toBe(1)
        ->and($property->fresh()->currentLandlordContact->id)->toBe($existing->id)
        ->and(RepairCase::firstOrFail()->property_landlord_contact_id)->toBe($existing->id);
});

/*
 * Snag #49(a) — the global email key is gone. Two tenants at the same
 * agency address get their own contact, and neither names it for the
 * other. This was impossible under the old unique index.
 */
it('gives two tenants their own contact for the same landlord address', function () {
    [$tenantA, $propertyA] = tenantWithProperty();
    [$tenantB, $propertyB] = tenantWithProperty();

    submitAndConfirm($tenantA, validStorePayload(
        $propertyA->id,
        ['landlord_email' => 'info@agency.example', 'landlord_name' => 'Agency Desk A']
    ));
    submitAndConfirm($tenantB, validStorePayload(
        $propertyB->id,
        ['landlord_email' => 'info@agency.example', 'landlord_name' => 'Agency Desk B']
    ));

    expect($propertyA->fresh()->currentLandlordContact->name)->toBe('Agency Desk A')
        ->and($propertyB->fresh()->currentLandlordContact->name)->toBe('Agency Desk B')
        ->and($propertyA->fresh()->currentLandlordContact->id)
        ->not->toBe($propertyB->fresh()->currentLandlordContact->id);
});

it('does not discard the typed landlord name when another tenant used that address first', function () {
    [$tenantA, $propertyA] = tenantWithProperty();
    [$tenantB, $propertyB] = tenantWithProperty();

    submitAndConfirm($tenantA, validStorePayload(
        $propertyA->id,
        ['landlord_email' => 'shared@example.com', 'landlord_name' => 'C Watts']
    ));
    submitAndConfirm($tenantB, validStorePayload(
        $propertyB->id,
        ['landlord_email' => 'shared@example.com', 'landlord_name' => 'Larry Landlord']
    ));

    // The exact gafol reproduction: the second tenant typed "Larry
    // Landlord" and the letter opened "Dear C Watts".
    $secondCase = RepairCase::where('tenant_user_id', $tenantB->id)->sole();

    expect($secondCase->messages()->whereNotNull('stage_at_send')->sole()->body_raw)
        ->toContain('Larry Landlord')
        ->not->toContain('C Watts');
});

/*
 * Snag #49(b) — the preview and the send must render one source.
 */
it('previews the salutation that the sent letter actually carries', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    test()->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['landlord_email' => 'typed@example.com', 'landlord_name' => 'Typed Name']
    ));

    test()->actingAs($tenant)->get('/cases/preview')
        ->assertSee('Stored Name', false)
        ->assertDontSee('Typed Name', false);

    test()->actingAs($tenant)->post('/cases/preview/confirm');

    expect(RepairCase::firstOrFail()->messages()->whereNotNull('stage_at_send')->sole()->body_raw)
        ->toContain('Stored Name')
        ->not->toContain('Typed Name');
});

it('serves the notice on the stored address, not the typed one', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    submitAndConfirm($tenant, validStorePayload(
        $property->id,
        ['landlord_email' => 'typed@example.com']
    ));

    expect(RepairCase::firstOrFail()->messages()->sole()->to_address_raw)
        ->toBe('stored@example.com');
});

it('accepts a submission with no landlord fields at all once the property has a contact', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    $payload = validStorePayload($property->id);
    unset($payload['landlord_email'], $payload['landlord_name'], $payload['landlord_role']);

    submitAndConfirm($tenant, $payload);

    expect(RepairCase::count())->toBe(1)
        ->and(RepairCase::firstOrFail()->messages()->sole()->to_address_raw)
        ->toBe('stored@example.com');
});

/**
 * Return the opening tag for an element by id.
 *
 * Used to assert there is exactly ONE class attribute on it. A literal
 * class="..." written alongside Blade's @class directive emits the
 * attribute twice; browsers keep the first and silently drop the second,
 * so the d-none never applies and both blocks render at once. The page
 * then shows a read-only "this property's landlord" panel next to an
 * editable set of landlord fields the server will ignore. Found by
 * walking the page in the running app — no assertion on behaviour could
 * have caught it.
 */
function openingTagFor(string $html, string $id): string
{
    expect($html)->toMatch('/<div[^>]*id="'.$id.'"[^>]*>/');
    preg_match('/<div[^>]*id="'.$id.'"[^>]*>/', $html, $m);

    return $m[0];
}

it('renders one class attribute per landlord block, so d-none is not dropped', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    $html = $this->actingAs($tenant)->get('/cases/create')->getContent();

    expect(substr_count(openingTagFor($html, 'landlord-fields'), 'class='))->toBe(1)
        ->and(substr_count(openingTagFor($html, 'landlord-inherited'), 'class='))->toBe(1);
});

it('hides the editable landlord fields once the property has a contact', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    $html = $this->actingAs($tenant)->get('/cases/create')->getContent();

    expect(openingTagFor($html, 'landlord-fields'))->toContain('d-none')
        ->and(openingTagFor($html, 'landlord-inherited'))->not->toContain('d-none');
});

it('points the correction link at the landlord contact page, not the property edit form', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    $html = $this->actingAs($tenant)->get('/cases/create')->getContent();

    // "Correct it on the property" used to link to properties.edit, which
    // does not carry the landlord contact at all — the sentence told the
    // tenant to do something the destination could not do. Found by
    // walking the page.
    expect($html)->toContain(route('properties.contact.edit', $property))
        ->and($html)->not->toContain(route('properties.edit', $property));
});

it('shows the editable landlord fields when the property has no contact', function () {
    [$tenant, $property] = tenantWithProperty();

    $html = $this->actingAs($tenant)->get('/cases/create')->getContent();

    expect(openingTagFor($html, 'landlord-fields'))->not->toContain('d-none')
        ->and(openingTagFor($html, 'landlord-inherited'))->toContain('d-none');
});

/*
 * Snag #59 — the preview must say WHO the notice is going to. Charlie,
 * walking the branch: "the preview does not show the landlords email
 * address, it would be re-assuring to do so".
 *
 * More than reassuring. #24 exists because a mistyped address was
 * permanent, and the preview is the last moment catching it is free.
 */
it('shows the recipient email on the preview when the property has no contact yet', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['landlord_email' => 'Typed@Example.com', 'landlord_name' => 'Larry Landlord']
    ));

    $this->actingAs($tenant)->get('/cases/preview')
        ->assertOk()
        ->assertSee('This notice will be sent to')
        // Normalised, so what is shown is what will actually be used.
        ->assertSee('typed@example.com')
        ->assertSee('Larry Landlord');
});

it('shows the STORED recipient email on the preview when the property has a contact', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'stored@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['landlord_email' => 'typed@example.com', 'landlord_name' => 'Typed Name']
    ));

    $this->actingAs($tenant)->get('/cases/preview')
        ->assertOk()
        ->assertSee('stored@example.com')
        ->assertDontSee('typed@example.com');
});

/*
 * The guard that matters: the address shown must be the address used.
 * A second resolver is exactly how #49(b) happened to the name, and
 * there is no reason the address would be immune.
 */
/*
 * Both sides are asserted against the SAME literal rather than by
 * holding the rendered page and comparing it to the sent row. Same
 * guarantee — if either surface drifts, one of the two fails — and it
 * keeps a full HTML page out of the test's memory, which matters in a
 * suite this size.
 */
it('previews the same address the notice is actually sent to', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['landlord_email' => 'Recipient@Example.com']
    ));

    $this->actingAs($tenant)->get('/cases/preview')->assertSee('recipient@example.com');
    $this->actingAs($tenant)->post('/cases/preview/confirm');

    expect(RepairCase::firstOrFail()->messages()->sole()->to_address_raw)
        ->toBe('recipient@example.com');
});

it('previews the same address the notice is sent to when the contact is inherited', function () {
    [$tenant, $property] = tenantWithProperty();
    $property->setLandlordContact(
        ['email' => 'inherited@example.com', 'name' => 'Stored Name', 'role' => 'landlord'],
        now(),
        $tenant->id,
    );

    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['landlord_email' => 'ignored@example.com']
    ));

    $this->actingAs($tenant)->get('/cases/preview')
        ->assertSee('inherited@example.com')
        ->assertDontSee('ignored@example.com');

    $this->actingAs($tenant)->post('/cases/preview/confirm');

    expect(RepairCase::firstOrFail()->messages()->sole()->to_address_raw)
        ->toBe('inherited@example.com');
});

it('shows the address alone rather than "Sir or Madam" when no name was given', function () {
    [$tenant, $property] = tenantWithProperty();

    $payload = validStorePayload($property->id, ['landlord_email' => 'noname@example.com']);
    unset($payload['landlord_name']);

    $this->actingAs($tenant)->post('/cases', $payload);

    // The letter still opens "Dear Sir or Madam" — that is the salutation
    // fallback, and it legitimately appears further down the page. The
    // recipient block must not repeat it as though it were a name, so the
    // check is scoped to that block rather than the whole page.
    $block = recipientBlock($this->actingAs($tenant)->get('/cases/preview')->getContent());

    expect($block)->toContain('noname@example.com')
        ->not->toContain('Sir or Madam');
});

/** Just the recipient block, so a whole page is not carried around. */
function recipientBlock(string $html): string
{
    $at = strpos($html, 'This notice will be sent to');

    return $at === false ? '' : substr($html, $at, 400);
}

it('still requires a landlord email when the property has no contact yet', function () {
    [$tenant, $property] = tenantWithProperty();

    $payload = validStorePayload($property->id);
    unset($payload['landlord_email']);

    test()->actingAs($tenant)
        ->post('/cases', $payload)
        ->assertSessionHasErrors('landlord_email');
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

it('states the limit the machine will actually accept, not our own cap', function () {
    [$tenant] = tenantWithProperty();

    $ours = 4096 * 1024;
    $php = FileSize::fromIniShorthand(ini_get('upload_max_filesize'));
    $effective = $php > 0 ? min($ours, $php) : $ours;

    $response = $this->actingAs($tenant)->get('/cases/create');

    $response->assertOk();
    // Advertising 4MB on a box PHP has configured for 2M promises something
    // that cannot happen — the tenant hits a refusal the form said wouldn't
    // come. The displayed figure and the byte limit handed to the script
    // both come from the same effective value.
    $response->assertSee('under '.FileSize::human($effective));
    $response->assertSee('data-photo-max-bytes="'.$effective.'"', false);
});

it('shows a photo error once, beside the input, not also in the page summary', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)
        ->from('/cases/create')
        ->post('/cases', validStorePayload($property->id) + [
            'photos' => [UploadedFile::fake()->create('kitchen-damp.jpg', 5000, 'image/jpeg')],
        ])
        ->assertRedirect('/cases/create');

    $page = $this->actingAs($tenant)->get('/cases/create');

    $page->assertSee('kitchen-damp.jpg');
    // The summary is for errors the tenant fixes elsewhere on the page. A
    // photo problem is fixed at the input, and rendering it in both places
    // showed the same message twice — and the script could only clear one.
    $page->assertDontSee('Please correct the following');
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
