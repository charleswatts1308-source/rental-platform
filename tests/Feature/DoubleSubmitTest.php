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
 *
 * Since #69 the reply is previewed first, so the token now lives on the
 * preview's confirm button rather than on the reply form. That is where a
 * double-click actually happens: the button that sends.
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

/** Stage a reply and hand back the token the preview rendered. */
function stageReply(User $tenant, RepairCase $case, string $body): string
{
    $html = test()->actingAs($tenant)
        ->post(route('cases.reply.preview', $case->url_slug), ['body' => $body])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="send_token"');
    preg_match('/name="send_token"\s*\n?\s*value="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
}

function outboundCount(RepairCase $case): int
{
    return CaseMessage::where('case_id', $case->id)
        ->where('direction', MessageDirection::Outbound)
        ->count();
}

it('writes ONE outbound row when the confirm is posted twice', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $token = stageReply($tenant, $case, 'The leak is worse today.');
    expect($token)->not->toBe('');

    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), ['send_token' => $token]);
    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), ['send_token' => $token]);

    expect(outboundCount($case))->toBe(1);
});

it('tells the tenant their reply was sent, rather than reporting a failure', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $token = stageReply($tenant, $case, 'Still leaking.');

    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), ['send_token' => $token]);

    // The tenant pressed send once as far as they are concerned, and it
    // DID go. An error here would be a lie.
    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), ['send_token' => $token])
        ->assertRedirect(route('cases.show', $case->url_slug))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();
});

it('still allows a genuine second reply, staged afresh', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $first = stageReply($tenant, $case, 'First message.');
    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), ['send_token' => $first]);

    $second = stageReply($tenant, $case, 'Second message, sent deliberately.');
    expect($second)->not->toBe($first);

    $this->actingAs($tenant)->post(route('cases.reply', $case->url_slug), ['send_token' => $second]);

    expect(outboundCount($case))->toBe(2);
});

/**
 * #69's own guard, which matters as much as the token: the confirm reads
 * the staged reply out of the session, so a confirm with nothing staged
 * must not send an empty letter or somebody else's.
 */
it('refuses a confirm with no staged reply behind it', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $this->actingAs($tenant)
        ->post(route('cases.reply', $case->url_slug), [])
        ->assertRedirect(route('cases.show', $case->url_slug))
        ->assertSessionHas('error');

    expect(outboundCount($case))->toBe(0);
});

it('does not send one tenant\'s staged reply on another tenant\'s case', function () {
    $tenant = User::factory()->create();
    $caseA = replyableCaseFor($tenant);
    $caseB = replyableCaseFor($tenant);

    $token = stageReply($tenant, $caseA, 'This belongs to case A.');

    // Same session, same tenant, wrong case.
    $this->actingAs($tenant)
        ->post(route('cases.reply', $caseB->url_slug), ['send_token' => $token])
        ->assertRedirect(route('cases.show', $caseB->url_slug));

    expect(outboundCount($caseB))->toBe(0);
});

it('puts the submit-once script on the page', function () {
    $tenant = User::factory()->create();
    $case = replyableCaseFor($tenant);

    $html = $this->actingAs($tenant)->get(route('cases.show', $case->url_slug))->getContent();

    expect($html)->toContain('form[method="POST"]');
    expect($html)->toContain('button.disabled = true');
});
