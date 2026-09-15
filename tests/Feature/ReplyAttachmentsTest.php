<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Models\CaseMessage;
use App\Models\MessageAttachment;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * #19 — attachments on tenant replies.
 *
 * Raised in the June 2026 gafol live-fire: a tenant replying about a
 * worsening problem wanted to photograph it and had no way to. Asked for
 * again 15 Sep 2026 and built the same day.
 *
 * The create-case path already staged, promoted and dispatched
 * attachments; the reply path called the same send action with the
 * attachment parameter empty. These tests pin that it no longer is, and
 * that a reply obeys the SAME limits as the create form rather than a
 * second set that could drift.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Setting::updateOrCreate(['key' => 'attachments.first_notice_max'], ['value' => '3']);
});

function repliableCase(User $tenant): RepairCase
{
    $category = RepairCategory::factory()->create();

    return RepairCase::factory()->withLandlord([])->create([
        'tenant_user_id' => $tenant->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => now()->subDays(3),
    ]);
}

it('attaches a photo to a tenant reply and records it on the outbound message', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), [
            'body' => 'The damp has spread since I last wrote — photo attached.',
            'photos' => [UploadedFile::fake()->image('worse.jpg')],
        ])
        ->assertRedirect();

    $message = CaseMessage::where('direction', MessageDirection::Outbound)
        ->latest('id')
        ->firstOrFail();

    $attachment = MessageAttachment::where('case_message_id', $message->id)->sole();

    expect($attachment->original_filename)->toBe('worse.jpg');
    expect($attachment->direction)->toBe(MessageDirection::Outbound);

    // Stored under the CASE folder, not the preview staging folder: a reply
    // has no preview to return from, and the daily sweep deletes preview
    // folders over 24h old (#45). Staging here would invent a window in
    // which a reply could silently lose its evidence.
    expect($attachment->path)->toStartWith('cases/'.$case->id.'/');
    Storage::disk($attachment->disk)->assertExists($attachment->path);
});

it('still sends a reply with no photos at all', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), ['body' => 'Just a text reply.'])
        ->assertRedirect();

    $message = CaseMessage::where('direction', MessageDirection::Outbound)
        ->latest('id')
        ->firstOrFail();

    expect(MessageAttachment::where('case_message_id', $message->id)->count())->toBe(0);
});

it('refuses more photos than the ceiling allows, and sends nothing', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);
    $before = CaseMessage::count();

    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), [
            'body' => 'Four photos, ceiling of three.',
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
                UploadedFile::fake()->image('d.jpg'),
            ],
        ])
        ->assertSessionHasErrors('photos');

    // Nothing sent. A rejected reply must not leave a letter on the record.
    expect(CaseMessage::count())->toBe($before);
});

it('refuses a file type that is not a photo or PDF', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), [
            'body' => 'Spreadsheet, not evidence.',
            'photos' => [UploadedFile::fake()->create('costs.xlsx', 10)],
        ])
        ->assertSessionHasErrors('photos.0');
});

it('names the tenant\'s own file when it refuses one, not an array index', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), [
            'body' => 'Too big.',
            'photos' => [UploadedFile::fake()->create('kitchen-ceiling.jpg', 9000, 'image/jpeg')],
        ]);

    $errors = session('errors')->getBag('default')->all();

    expect(implode(' ', $errors))->toContain('kitchen-ceiling.jpg');
    expect(implode(' ', $errors))->not->toContain('photos.0');
});

it('offers the photo field on the case page, with the same limits as the create form', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)
        ->get(route('cases.show', $case->url_slug))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="photos[]"');
    // Without enctype the browser posts filenames, not files, and the
    // tenant believes photos went when they did not.
    expect($html)->toContain('enctype="multipart/form-data"');
    expect($html)->toMatch('/Up to <strong>3<\/strong> files/');
    expect($html)->toContain('each under <strong>'.\App\Support\PhotoLimits::perFileLabel().'</strong>');
});

it('says why, rather than showing nothing, when the ceiling is zero', function () {
    Setting::updateOrCreate(['key' => 'attachments.first_notice_max'], ['value' => '0']);

    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)
        ->get(route('cases.show', $case->url_slug))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('name="photos[]"');
    expect($html)->toContain('Photos can&rsquo;t be attached at the moment');
});
