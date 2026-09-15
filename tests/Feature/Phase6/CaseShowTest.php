<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the detail page for the owning tenant', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);

    $response = $this->actingAs($tenant)->get("/cases/{$case->url_slug}");

    $response->assertOk();
    $response->assertSee($case->url_slug);
    $response->assertSee('awaiting landlord', false);
});

it('returns 403 when a tenant tries to view another tenant\'s case', function () {
    $tenant = User::factory()->create();
    $other = User::factory()->create();
    $foreign = RepairCase::factory()->create(['tenant_user_id' => $other->id]);

    $response = $this->actingAs($tenant)->get("/cases/{$foreign->url_slug}");

    $response->assertForbidden();
});

it('returns 404 for a slug that does not exist', function () {
    $tenant = User::factory()->create();

    $response = $this->actingAs($tenant)->get('/cases/doesnotexist');

    $response->assertNotFound();
});

it('redirects guests away from the detail page', function () {
    $case = RepairCase::factory()->create();

    $response = $this->get("/cases/{$case->url_slug}");

    $response->assertRedirect('/login');
});

it('renders inbound and outbound messages in the main thread', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingTenantReview,
    ]);

    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'subject' => 'Initial repair notice',
        'tenant_statement' => 'There is mould throughout the bathroom.',
    ]);
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Inbound,
        'sender_role' => SenderRole::Landlord,
        'subject' => 'Re: Repair notice',
        'body_sanitised' => '<p>Will arrange a contractor next week.</p>',
        'received_at' => now(),
    ]);

    $response = $this->actingAs($tenant)->get("/cases/{$case->url_slug}");

    $response->assertOk();
    $response->assertSee('There is mould throughout the bathroom.');
    $response->assertSee('Will arrange a contractor next week.', false);
});

it('hides quarantined inbound messages from the main thread and surfaces them in the warning section', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingTenantReview,
    ]);

    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Inbound,
        'sender_role' => SenderRole::Landlord,
        'body_sanitised' => '<p>Trustworthy reply.</p>',
        'received_at' => now(),
    ]);
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Inbound,
        'sender_role' => SenderRole::Landlord,
        'body_sanitised' => '<p>Suspicious unverified reply.</p>',
        'quarantine_reason' => 'unexpected_from_address',
        'received_at' => now(),
    ]);

    $response = $this->actingAs($tenant)->get("/cases/{$case->url_slug}");

    $response->assertOk();
    $response->assertSee('Trustworthy reply.', false);
    $response->assertSee('Unverified messages');
    $response->assertSee('Suspicious unverified reply.', false);
});

it('does not show the unverified-messages warning when there are no quarantined messages', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);

    $response = $this->actingAs($tenant)->get("/cases/{$case->url_slug}");

    $response->assertOk();
    $response->assertDontSee('Unverified messages');
});

/*
 * #2 — the case page never showed the landlord's EMAIL, only a name, and
 * titled the panel with the generic "Recipient" whether the contact was a
 * landlord or a managing agent. The email is the one detail a case
 * depends on and the tenant had no way to check it.
 */

it('#2 — shows the landlord email on the case page and titles the panel by role', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);

    $case->property->setLandlordContact(
        [
            'email' => 'mr.eastlord@example.com',
            'name' => 'Mr EastLord',
            'role' => App\Enums\LandlordContactRole::Landlord,
        ],
        now(),
        $tenant->id,
    );

    $response = $this->actingAs($tenant)->get("/cases/{$case->url_slug}");

    $response->assertOk();
    $response->assertSee('mr.eastlord@example.com');
    $response->assertSee('Mr EastLord');
    $response->assertSee('Landlord');
    $response->assertDontSee('Recipient');
});

it('#2 — titles the panel Agent when the contact is a managing agent', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);

    $case->property->setLandlordContact(
        [
            'email' => 'lettings@example-agents.com',
            'name' => 'Example Lettings',
            'role' => App\Enums\LandlordContactRole::Agent,
            'organisation_name' => 'Example Agents Ltd',
        ],
        now(),
        $tenant->id,
    );

    $response = $this->actingAs($tenant)->get("/cases/{$case->url_slug}");

    $response->assertOk();
    $response->assertSee('Agent');
    $response->assertSee('lettings@example-agents.com');
    $response->assertSee('Example Agents Ltd');
});
