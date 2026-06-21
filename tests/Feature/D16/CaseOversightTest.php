<?php

use App\Enums\CaseStatus;
use App\Models\CaseEvent;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function oversightAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

// ---- gate -------------------------------------------------------------

it('forbids a non-admin on the oversight routes', function () {
    $user = User::factory()->create();
    $case = RepairCase::factory()->create();

    $this->actingAs($user)->get('/admin/cases')->assertForbidden();
    $this->actingAs($user)->get("/admin/cases/{$case->id}")->assertForbidden();
});

// ---- read-only listing + detail --------------------------------------

it('lists cases for an admin by reference', function () {
    $case = RepairCase::factory()->create();

    $this->actingAs(oversightAdmin())
        ->get('/admin/cases')
        ->assertOk()
        ->assertSee($case->url_slug);
});

it('shows a read-only case detail with the event trail', function () {
    $case = RepairCase::factory()->create();
    CaseEvent::factory()->create([
        'case_id' => $case->id,
        'event_type' => 'case_opened',
        'actor_label' => 'system',
    ]);

    $this->actingAs(oversightAdmin())
        ->get("/admin/cases/{$case->id}")
        ->assertOk()
        ->assertSee('Read-only')
        ->assertSee('Event trail')
        ->assertSee('case_opened');
});

it('exposes no case-mutating controls in oversight (read-only)', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::EscalationExhausted,
    ]);

    $this->actingAs(oversightAdmin())
        ->get("/admin/cases/{$case->id}")
        ->assertOk()
        ->assertDontSee('Send reply')
        ->assertDontSee('Mark resolved')
        ->assertDontSee('Abandon this case');
});

// ---- oversight reuses the shared predicate ---------------------------

it('oversight uses the shared predicate: next escalation shown when counting down', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
        'silence_settings_snapshot' => [
            'escalation.interval_days' => 14,
            'escalation.max_notices' => 4,
            'nudge.first_days' => 10,
            'nudge.second_days' => 20,
            'nudge.dormancy_days' => 30,
        ],
    ]);

    $this->actingAs(oversightAdmin())
        ->get("/admin/cases/{$case->id}")
        ->assertOk()
        ->assertSee('Next escalation')
        ->assertSee('15 Jun 2026');
});

it('oversight uses the shared predicate: no next escalation on_hold (#14)', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'ball_with' => 'landlord',
        'hold_until' => Carbon::parse('2026-07-01'),
        'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
        'silence_settings_snapshot' => [
            'escalation.interval_days' => 14,
            'escalation.max_notices' => 4,
            'nudge.first_days' => 10,
            'nudge.second_days' => 20,
            'nudge.dormancy_days' => 30,
        ],
    ]);

    $this->actingAs(oversightAdmin())
        ->get("/admin/cases/{$case->id}")
        ->assertOk()
        ->assertDontSee('Next escalation')
        ->assertSee('Hold until');
});
