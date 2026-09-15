<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * #71 — a double-click on Send posted the letter twice.
 *
 * Found by Charlie on 15 Sep 2026 doing exactly what a user does: "funny
 * thing i double clicked on send by mistake and did send 2 emails to
 * mailpit". Case LYE62E on dev took two outbound rows a second apart and
 * the landlord received two letters.
 *
 * Outbound rows are the evidence record, so a duplicate is not cosmetic.
 * On the ESCALATION path it is worse than duplication: the counter is
 * derived from these rows and never resets (D3), so a second letter
 * advances the ladder with no way back.
 *
 * Two layers, and these tests cover the one that matters. The browser
 * disables the button; the server refuses the repeat. A double-submit
 * that never touches the script — two tabs, a resubmitted back button —
 * still has to be refused.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
});

function replyableCaseFor(User $tenant): RepairCase
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

/** Read the token the page actually rendered, as a second click would. */
function sendTokenFrom(string $html): string
{
    expect($html)->toContain('name="send_token"');
    preg_match('/name="send_token"\s*\n?\s*value="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
}

it('writes ONE outbound row when the same reply is posted twice', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->getContent();
    $token = sendTokenFrom($html);
    expect($token)->not->toBe('');

    $payload = ['body' => 'The leak is worse today.', 'send_token' => $token];

    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), $payload);
    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), $payload);

    expect(CaseMessage::where('case_id', $case->id)
        ->where('direction', MessageDirection::Outbound)
        ->count())->toBe(1);
});

it('tells the tenant their reply was sent, rather than reporting a failure', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->getContent();
    $payload = ['body' => 'Still leaking.', 'send_token' => sendTokenFrom($html)];

    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), $payload);

    // The tenant pressed send once as far as they are concerned, and it
    // DID go. An error here would be a lie.
    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), $payload)
        ->assertRedirect(route('cases.show', $case->url_slug))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();
});

it('still allows a genuine second reply from a freshly loaded page', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $first = sendTokenFrom($this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->getContent());
    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), [
        'body' => 'First message.', 'send_token' => $first,
    ]);

    $second = sendTokenFrom($this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->getContent());
    expect($second)->not->toBe($first);

    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), [
        'body' => 'Second message, sent deliberately.', 'send_token' => $second,
    ]);

    expect(CaseMessage::where('case_id', $case->id)
        ->where('direction', MessageDirection::Outbound)
        ->count())->toBe(2);
});

it('does not refuse a reply that carries no token at all', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    // A page rendered before #71, or a cleared session. Losing a tenant's
    // genuine message is worse than the duplicate this guards against, so
    // an absent token is treated as valid.
    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), [
        'body' => 'No token on this one.',
    ])->assertRedirect();

    expect(CaseMessage::where('case_id', $case->id)
        ->where('direction', MessageDirection::Outbound)
        ->count())->toBe(1);
});

it('puts the submit-once script on the page', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->getContent();

    expect($html)->toContain("form[method=\"POST\"]");
    expect($html)->toContain('button.disabled = true');
});
