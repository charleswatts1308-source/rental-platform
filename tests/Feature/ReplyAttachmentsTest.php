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

/**
 * Charlie, 15 Sep: "the attachments are not being listed as in the create
 * case page". The reply form took files but showed nothing back, so a
 * tenant could not see what they had chosen before sending — on the one
 * form whose whole purpose is evidence.
 *
 * Fixed by SHARING the create form's picker rather than writing a second
 * one. These assertions pin that it is genuinely the same partial on both
 * forms: a reply that refused a file for a different reason, or quoted a
 * different size, would be the drift #68 was.
 */
it('lists the chosen files on the reply form, using the same picker as the create form', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)
        ->get(route('cases.show', $case->url_slug))
        ->assertOk()
        ->getContent();

    // Somewhere to list them, somewhere to explain a refusal.
    expect($html)->toContain('id="reply-photo-list"');
    expect($html)->toContain('id="reply-photo-errors"');

    // The picker is wired to THIS form's ids.
    expect($html)->toContain('document.getElementById("reply_photos")');
    expect($html)->toContain('document.getElementById("reply-photo-list")');

    // And it is handed the same numbers the server enforces.
    expect($html)->toContain('data-photo-ceiling="3"');
    expect($html)->toContain('data-photo-max-bytes="'.\App\Support\PhotoLimits::perFileBytes().'"');
});

it('carries the same accumulate-and-total behaviour on both forms', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $replyHtml = $this->actingAs($tenant)
        ->get(route('cases.show', $case->url_slug))->getContent();
    $createHtml = $this->actingAs($tenant)
        ->get('/cases/create')->getContent();

    // A sentence from the shared picker, on both pages. If someone forks
    // the script, one of these stops matching.
    $marker = 'would take the total over';

    expect($replyHtml)->toContain($marker);
    expect($createHtml)->toContain($marker);
});

/**
 * #70(b) — the reply form sits in the main column beneath the
 * correspondence, not in the sidebar. It was a sidebar widget when it was
 * a text box and a button; #19 gave it a picker and a file list, and a
 * third of the page is not enough room for that.
 *
 * Asserted by ORDER rather than by markup: the form must come after the
 * thread it answers. That survives restyling and fails if anyone moves it
 * back above the correspondence or into the sidebar block.
 */
it('puts the reply form after the correspondence, not in the sidebar', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)
        ->get(route('cases.show', $case->url_slug))
        ->assertOk()
        ->getContent();

    $actions = strpos($html, 'Available actions');
    $correspondence = strpos($html, 'Correspondence');
    $replyForm = strpos($html, 'name="photos[]"');

    expect($actions)->toBeLessThan($correspondence);
    expect($correspondence)->toBeLessThan($replyForm);
});

it('keeps the other case actions in the sidebar', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)
        ->get(route('cases.show', $case->url_slug))
        ->assertOk()
        ->getContent();

    // Pause and the rest did NOT move — only the reply did.
    expect(strpos($html, 'Pause case'))->toBeLessThan(strpos($html, 'Correspondence'));
});
