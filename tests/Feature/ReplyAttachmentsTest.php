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
 * #69 — and the reply is previewed before it is sent, like letter 1.
 *
 * #19 was raised in the June 2026 gafol live-fire: a tenant replying about
 * a worsening problem wanted to photograph it and had no way to. #69 came
 * straight out of walking #19 — Charlie: "I was subconsciously expecting a
 * preview as with letter 1" — and was ruled the same day.
 *
 * The flow is now write -> preview -> confirm, the same three steps as
 * raising a case.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Setting::updateOrCreate(['key' => 'attachments.first_notice_max'], ['value' => '3']);
    // #73 — the reply carries its OWN ceiling now.
    Setting::updateOrCreate(['key' => 'attachments.reply_max'], ['value' => '3']);
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

/** The token the preview rendered, as the confirm button carries it. */
function tokenFrom(string $html): string
{
    preg_match('/name="send_token"\s*\n?\s*value="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
}

/** Write, preview, confirm — in one call. */
function previewAndSend(User $tenant, RepairCase $case, array $payload): void
{
    $html = test()->actingAs($tenant)
        ->post(route('cases.reply.preview', $case->url_slug), $payload)
        ->assertOk()
        ->getContent();

    test()->actingAs($tenant)->post(route('cases.reply', $case->url_slug), [
        'send_token' => tokenFrom($html),
    ]);
}

it('attaches a photo to a tenant reply and records it on the outbound message', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    previewAndSend($tenant, $case, [
        'body' => 'The damp has spread since I last wrote — photo attached.',
        'photos' => [UploadedFile::fake()->image('worse.jpg')],
    ]);

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->latest('id')->firstOrFail();
    $attachment = MessageAttachment::where('case_message_id', $message->id)->sole();

    expect($attachment->original_filename)->toBe('worse.jpg');
    expect($attachment->direction)->toBe(MessageDirection::Outbound);

    // Promoted out of the staging folder into the case folder on confirm,
    // exactly as the create flow does.
    expect($attachment->path)->toStartWith('cases/'.$case->id.'/');
    Storage::disk($attachment->disk)->assertExists($attachment->path);
});

it('still sends a reply with no photos at all', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    previewAndSend($tenant, $case, ['body' => 'Just a text reply.']);

    $message = CaseMessage::where('direction', MessageDirection::Outbound)->latest('id')->firstOrFail();

    expect(MessageAttachment::where('case_message_id', $message->id)->count())->toBe(0);
});

it('refuses more photos than the ceiling allows, and previews nothing', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply.preview', $case->url_slug), [
            'body' => 'Four photos, ceiling of three.',
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
                UploadedFile::fake()->image('d.jpg'),
            ],
        ])
        ->assertSessionHasErrors('photos');

    expect(CaseMessage::where('case_id', $case->id)->count())->toBe(0);
});

it('refuses a file type that is not a photo or PDF', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply.preview', $case->url_slug), [
            'body' => 'Spreadsheet, not evidence.',
            'photos' => [UploadedFile::fake()->create('costs.xlsx', 10)],
        ])
        ->assertSessionHasErrors('photos.0');
});

it('names the file the tenant chose when it refuses one, not an array index', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply.preview', $case->url_slug), [
            'body' => 'Too big.',
            'photos' => [UploadedFile::fake()->create('kitchen-ceiling.jpg', 9000, 'image/jpeg')],
        ]);

    $errors = implode(' ', session('errors')->getBag('default')->all());

    expect($errors)->toContain('kitchen-ceiling.jpg');
    expect($errors)->not->toContain('photos.0');
});

it('offers the photo field on the case page, with the same limits as the create form', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->assertOk()->getContent();

    expect($html)->toContain('name="photos[]"');
    expect($html)->toContain('enctype="multipart/form-data"');
    expect($html)->toMatch('/Up to <strong>3<\/strong> files/');
    expect($html)->toContain('each under <strong>'.\App\Support\PhotoLimits::perFileLabel().'</strong>');
});

it('says why, rather than showing nothing, when the REPLY ceiling is zero', function () {
    Setting::updateOrCreate(['key' => 'attachments.reply_max'], ['value' => '0']);

    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->assertOk()->getContent();

    expect($html)->not->toContain('name="photos[]"');
    expect($html)->toContain('Photos can&rsquo;t be attached at the moment');
});

it('lists the chosen files on the reply form, using the same picker as the create form', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->assertOk()->getContent();

    expect($html)->toContain('id="reply-photo-list"');
    expect($html)->toContain('id="reply-photo-errors"');
    expect($html)->toContain('document.getElementById("reply_photos")');
    expect($html)->toContain('data-photo-ceiling="3"');
    expect($html)->toContain('data-photo-max-bytes="'.\App\Support\PhotoLimits::perFileBytes().'"');
});

it('carries the same accumulate-and-total behaviour on both forms', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $replyHtml = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->getContent();
    $createHtml = $this->actingAs($tenant)->get('/cases/create')->getContent();

    $marker = 'would take the total over';

    expect($replyHtml)->toContain($marker);
    expect($createHtml)->toContain($marker);
});

it('puts the reply form after the correspondence, not in the sidebar', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->assertOk()->getContent();

    expect(strpos($html, 'Available actions'))->toBeLessThan(strpos($html, 'Correspondence'));
    expect(strpos($html, 'Correspondence'))->toBeLessThan(strpos($html, 'name="photos[]"'));
});

it('keeps the other case actions in the sidebar', function () {
    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->assertOk()->getContent();

    expect(strpos($html, 'Pause case'))->toBeLessThan(strpos($html, 'Correspondence'));
});

/**
 * #73 — the two ceilings are independent, which is the whole point.
 *
 * A ceiling of 0 exists on deliverability grounds, and the risk it guards
 * against is a COLD letter to a stranger carrying an attachment. Once the
 * landlord has written back, that risk has largely gone. So an
 * installation can refuse photos on letter 1 and still allow them on a
 * reply — and only because that is true may the create form promise it.
 */
it('still offers photos on a reply when letter 1 refuses them', function () {
    Setting::updateOrCreate(['key' => 'attachments.first_notice_max'], ['value' => '0']);
    Setting::updateOrCreate(['key' => 'attachments.reply_max'], ['value' => '3']);

    $tenant = User::factory()->create();
    $case = repliableCase($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->assertOk()->getContent();

    expect($html)->toContain('name="photos[]"');
    expect($html)->toMatch('/Up to <strong>3<\/strong> files/');
});

it('promises photos later on the create form ONLY when a reply could carry them', function () {
    $tenant = User::factory()->create();
    \App\Models\Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    $promise = 'able to attach photos once your landlord replies';

    Setting::updateOrCreate(['key' => 'attachments.first_notice_max'], ['value' => '0']);
    Setting::updateOrCreate(['key' => 'attachments.reply_max'], ['value' => '3']);

    expect($this->actingAs($tenant)->get('/cases/create')->getContent())->toContain($promise);

    // Both off: the promise would be false, so it is not made. A surface
    // must not claim what the system cannot deliver.
    Setting::updateOrCreate(['key' => 'attachments.reply_max'], ['value' => '0']);

    expect($this->actingAs($tenant)->get('/cases/create')->getContent())->not->toContain($promise);
});