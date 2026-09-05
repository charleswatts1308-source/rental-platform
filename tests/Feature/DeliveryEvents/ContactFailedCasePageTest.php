<?php

use App\Enums\CaseStatus;
use App\Models\CaseMessage;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * #25 — what the tenant sees on a case stopped by a delivery failure.
 *
 * Without this the tenant gets an email saying their notice could not be
 * delivered, clicks through, and finds a status badge reading "contact
 * failed" and nothing else. That is the #46/#49/#53 pattern: a surface
 * that does not tell the tenant what the system has done.
 *
 * A bounce and a complaint are evidentially opposite (D17.5) and the page
 * must not conflate them — one has an address to correct, the other
 * proves the letter arrived.
 */
uses(RefreshDatabase::class);

function stoppedCase(string $eventType, string $recipient = 'landlord@example.com'): array
{
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'property_id' => $property->id,
        'status' => CaseStatus::ContactFailed,
    ]);

    $message = CaseMessage::factory()->create(['case_id' => $case->id]);

    $case->events()->create([
        'event_type' => $eventType,
        'actor_label' => 'system',
        'occurred_at' => now(),
        'meta' => [
            'case_message_id' => $message->id,
            'mailgun_event_id' => 'evt-1',
            'recipient' => $recipient,
        ],
    ]);

    return [$tenant, $case];
}

it('explains a bounce, and names the address that failed', function () {
    [$tenant, $case] = stoppedCase('delivery_failed', 'wrong@example.com');

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertSee('could not be delivered')
        ->assertSee('wrong@example.com')
        // The one thing the tenant can act on.
        ->assertSee(route('properties.contact.edit', $case->property));
});

it('says plainly that the notice was NOT received', function () {
    // The whole point. A case that stopped must not read as one that was
    // served and ignored — that is the falsehood #25 exists to prevent.
    [$tenant, $case] = stoppedCase('delivery_failed');

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertSee('not</strong> been received', false);
});

it('explains a complaint differently — it ARRIVED', function () {
    // D17.5's asymmetry. Offering to correct the address here would be
    // nonsense: there is nothing wrong with it.
    [$tenant, $case] = stoppedCase('delivery_complained');

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertSee('reported as spam')
        ->assertSee('It did arrive')
        ->assertDontSee('could not be delivered');
});

it('shows nothing on a case that has not been stopped', function () {
    $tenant = User::factory()->create();
    $property = Property::factory()->create(['registered_by_user_id' => $tenant->id]);
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'property_id' => $property->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertDontSee('could not be delivered')
        ->assertDontSee('reported as spam');
});

it('offers the tenant the one exit D17.8 allows, and no others', function () {
    // The state machine permits contact_failed → abandoned and nothing
    // else. abandon() used to delegate straight to resolve(), whose
    // allow-list excludes contact_failed — so the transition was legal and
    // the tenant had no way to take it.
    [$tenant, $case] = stoppedCase('delivery_failed');

    expect($tenant->can('abandon', $case))->toBeTrue()
        ->and($tenant->can('resolve', $case))->toBeFalse()
        ->and($tenant->can('reply', $case))->toBeFalse()
        ->and($tenant->can('hold', $case))->toBeFalse();
});

it('actually lets the tenant abandon a stopped case', function () {
    [$tenant, $case] = stoppedCase('delivery_failed');

    $this->actingAs($tenant)
        ->post("/cases/{$case->url_slug}/abandon", ['reason' => 'Raising a fresh one'])
        ->assertRedirect();

    expect($case->fresh()->status)->toBe(CaseStatus::Abandoned);
});

it('does not leak the panel to somebody else\'s case', function () {
    [, $case] = stoppedCase('delivery_failed');
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get("/cases/{$case->url_slug}")
        ->assertForbidden();
});
