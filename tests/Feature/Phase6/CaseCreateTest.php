<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
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

it('creates a case, mints a token, queues the notice, and transitions to awaiting_landlord', function () {
    [$tenant, $property] = tenantWithProperty();

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload($property->id));

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

it('writes the description as the outbound message tenant_statement', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['description' => 'The boiler has been silent for nine days.']
    ));

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->firstOrFail();
    expect($message->tenant_statement)->toBe('The boiler has been silent for nine days.');
});

it('reuses an existing landlord_contact when the email matches', function () {
    [$tenant, $property] = tenantWithProperty();
    $existing = LandlordContact::factory()->create(['email' => 'shared@example.com']);

    $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['landlord_email' => 'SHARED@example.com']
    ));

    expect(LandlordContact::where('email', 'shared@example.com')->count())->toBe(1);
    expect(RepairCase::firstOrFail()->landlord_contact_id)->toBe($existing->id);
});

it('creates a new landlord_contact when the email is unknown', function () {
    [$tenant, $property] = tenantWithProperty();

    $this->actingAs($tenant)->post('/cases', validStorePayload(
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

    $files = [
        UploadedFile::fake()->image('mould-1.jpg'),
        UploadedFile::fake()->image('mould-2.png'),
    ];

    $this->actingAs($tenant)->post('/cases', validStorePayload($property->id) + [
        'photos' => $files,
    ]);

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

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload($property->id));

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
        'landlord_email',
        'landlord_role',
    ]);
    expect(RepairCase::count())->toBe(0);
});

it('requires a description when the chosen category requires one', function () {
    RepairCategory::factory()->create([
        'key' => 'other',
        'label' => 'Other',
        'active' => true,
        'requires_description' => true,
    ]);
    [$tenant, $property] = tenantWithProperty();

    $response = $this->actingAs($tenant)->post('/cases', validStorePayload(
        $property->id,
        ['category_key' => 'other', 'description' => '']
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

it('redirects guests away from POST /cases', function () {
    $response = $this->post('/cases', []);

    $response->assertRedirect('/login');
});
