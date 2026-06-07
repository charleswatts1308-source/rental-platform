<?php

use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\Property;
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

function postCaseWithPhotos(User $tenant, Property $property, array $files): void
{
    test()->actingAs($tenant)->post('/cases', [
        'property_id' => $property->id,
        'category_key' => 'damp_mould',
        'severity' => 'routine',
        'description' => 'Mould has spread.',
        'landlord_email' => 'landlord@example.com',
        'landlord_role' => 'landlord',
        'photos' => $files,
    ]);
    // Phase 3 D13 — second POST confirms the preview and actually fires the send.
    test()->actingAs($tenant)->post('/cases/preview/confirm');
}

it('attaches uploaded photos to the queued CaseNotice mailable', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    postCaseWithPhotos($tenant, $property, [
        UploadedFile::fake()->image('mould-a.jpg'),
        UploadedFile::fake()->image('mould-b.png'),
    ]);

    Mail::assertQueued(CaseNotice::class, function (CaseNotice $mail) {
        return count($mail->attachments()) === 2;
    });
});

it('queues the mailable with the rendered message attached, even when no photos are uploaded', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    $this->actingAs($tenant)->post('/cases', [
        'property_id' => $property->id,
        'category_key' => 'damp_mould',
        'severity' => 'routine',
        'description' => 'Heater dead.',
        'landlord_email' => 'landlord@example.com',
        'landlord_role' => 'landlord',
    ]);
    $this->actingAs($tenant)->post('/cases/preview/confirm');

    Mail::assertQueued(CaseNotice::class, function (CaseNotice $mail) {
        return $mail->attachments() === [];
    });
});

it('persists outbound message_attachments before the mailable is queued', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    postCaseWithPhotos($tenant, $property, [
        UploadedFile::fake()->image('photo.jpg'),
    ]);

    $message = CaseMessage::firstOrFail();
    expect($message->attachments()->count())->toBe(1);
});
