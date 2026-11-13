<?php

use App\Enums\CaseStatus;
use App\Models\CaseMessage;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\RepairCase;
use App\Models\SilenceShadowLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function dashboardAdmin(): User
{
    $admin = User::factory()->create([
        'email' => 'boss@example.test',
        'created_at' => Carbon::parse('2020-01-01'),
    ]);
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function shadowRun(RepairCase $case, string $at, ?string $pretendToday = null): void
{
    SilenceShadowLog::create([
        'case_id' => $case->id,
        'swept_at' => Carbon::parse($at),
        'intended_action' => 'no_action',
        'reasoning' => 'test',
        'is_pretend' => $pretendToday !== null,
        'pretend_today' => $pretendToday,
    ]);
}

// ---- gate ------------------------------------------------------------------

it('sends a guest to login and forbids a non-admin', function () {
    $this->get('/admin')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('puts Dashboard in the admin menu', function () {
    $this->actingAs(dashboardAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('href="' . route('admin.dashboard') . '"', false);
});

// ---- registrations -----------------------------------------------------

it('shows the running registration total and only the 20 newest, newest first', function () {
    $admin = dashboardAdmin();

    $oldest = User::factory()->create([
        'email' => 'oldest@example.test',
        'created_at' => Carbon::parse('2026-01-01 09:00'),
    ]);
    foreach (range(1, 20) as $i) {
        User::factory()->create([
            'email' => "user{$i}@example.test",
            'created_at' => Carbon::parse('2026-02-01')->addDays($i),
        ]);
    }

    $response = $this->actingAs($admin)->get('/admin')->assertOk();

    // 1 admin + 1 oldest + 20 = 22, and only 20 rows listed.
    expect($response->getContent())->toMatch('/Registrations<\/div>\s*<div class="fs-3">22<\/div>/');
    $response->assertSee('user20@example.test');
    $response->assertSee('user1@example.test');
    $response->assertDontSee('oldest@example.test');
    $response->assertSeeInOrder(['user20@example.test', 'user19@example.test', 'user1@example.test']);
});

it('counts each registration\'s properties and cases', function () {
    $tenant = User::factory()->create(['email' => 'tenant@example.test']);
    RepairCase::factory()->count(2)->create(['tenant_user_id' => $tenant->id]);

    $response = $this->actingAs(dashboardAdmin())->get('/admin')->assertOk();

    $html = $response->getContent();
    $row = substr($html, strpos($html, 'tenant@example.test'));
    $row = substr($row, 0, strpos($row, '</tr>'));
    // Two cases; the factory makes a property per case but registers it to
    // a different factory user, so properties for this tenant = 0.
    expect($row)->toMatch('/<td class="text-end">0<\/td>\s*<td class="text-end">2<\/td>/');
});

// ---- cases ---------------------------------------------------------------

it('shows the case total, status breakdown, and the newest cases with letters and replies', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'opened_at' => Carbon::parse('2026-11-01'),
    ]);
    CaseMessage::factory()->count(2)->create(['case_id' => $case->id]);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);

    $older = RepairCase::factory()->create(['opened_at' => Carbon::parse('2026-10-01')]);

    $response = $this->actingAs(dashboardAdmin())->get('/admin')->assertOk();

    $response->assertSee('awaiting landlord');
    $response->assertSee('href="' . route('admin.cases.show', $case) . '"', false);
    $response->assertSeeInOrder([$case->url_slug, $older->url_slug]);
    $response->assertSee('Yes (1)');
});

it('lists only the 20 newest cases', function () {
    $oldest = RepairCase::factory()->create(['opened_at' => Carbon::parse('2025-01-01')]);
    RepairCase::factory()->count(20)->create(['opened_at' => Carbon::parse('2026-06-01')]);

    $this->actingAs(dashboardAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee($oldest->url_slug);
});

// ---- scheduler health --------------------------------------------------

it('shows real sweep runs per day and ignores pretend sweeps', function () {
    $this->travelTo(Carbon::parse('2026-11-13 12:00'));
    $case = RepairCase::factory()->create();

    shadowRun($case, '2026-11-13 06:15:00');
    shadowRun($case, '2026-11-12 06:15:00');
    shadowRun($case, '2026-11-12 07:00:00');           // a second run that day
    shadowRun($case, '2026-11-11 06:15:00', '2026-12-01'); // pretend — ignored

    $html = $this->actingAs(dashboardAdmin())->get('/admin')->assertOk()->getContent();

    expect($html)->toContain('Today: 1 run.')
        ->and($html)->toMatch('/Thu 12 Nov<\/td>\s*<td class="text-end text-danger fw-semibold">2</')
        ->and($html)->toMatch('/Wed 11 Nov<\/td>\s*<td class="text-end text-danger fw-semibold">0</')
        ->and($html)->toContain('Last real run: 13 Nov 2026 06:15');
});

it('flags today in red when no sweep has run', function () {
    $this->travelTo(Carbon::parse('2026-11-13 12:00'));

    $this->actingAs(dashboardAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Today: 0 run(s).')
        ->assertSee('Last real run: never');
});

// ---- needs attention -------------------------------------------------

it('lists unanswered contact messages and undeliverable cases, not answered ones', function () {
    $user = User::factory()->create(['email' => 'asker@example.test']);
    $open = ContactMessage::forceCreate([
        'user_id' => $user->id, 'subject' => 'Still waiting', 'message' => 'x',
        'created_at' => now(),
    ]);
    ContactMessage::forceCreate([
        'user_id' => $user->id, 'subject' => 'Already answered', 'message' => 'x',
        'admin_reply' => 'done', 'replied_at' => now(), 'created_at' => now(),
    ]);
    $failed = RepairCase::factory()->create(['status' => CaseStatus::ContactFailed]);

    $this->actingAs(dashboardAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Still waiting')
        ->assertSee('href="' . route('admin.contact-messages.show', $open) . '"', false)
        ->assertDontSee('Already answered')
        ->assertSee('Letter could not be delivered')
        ->assertSee($failed->url_slug);
});

it('says nothing is waiting when nothing is', function () {
    $this->actingAs(dashboardAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Nothing waiting.');
});

// ---- visitors --------------------------------------------------------

it('says tracking is off when it is off and nothing was recorded', function () {
    config(['app.track_page_views' => false]);

    $this->actingAs(dashboardAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Page tracking is switched off on this site');
});

it('counts human visitors per day and top pages, excluding crawlers', function () {
    $this->travelTo(Carbon::parse('2026-11-13 12:00'));
    config(['app.track_page_views' => false]); // keep the admin's own GET out

    $row = fn (string $page, string $ip, string $ua) => PageView::create([
        'view_date_time' => now(),
        'page_name' => "https://renters.rent{$page}",
        'ip_address' => $ip,
        'user_agent' => $ua,
    ]);
    $row('/prs', '1.1.1.1', 'Mozilla/5.0');
    $row('/prs', '1.1.1.1', 'Mozilla/5.0');
    $row('/prs', '2.2.2.2', 'Mozilla/5.0');
    $row('/about', '2.2.2.2', 'Mozilla/5.0');
    $row('/secret-crawl', '9.9.9.9', 'Googlebot/2.1');

    $html = $this->actingAs(dashboardAdmin())->get('/admin')->assertOk()->getContent();

    // Today: 2 distinct IPs, 4 human views.
    expect($html)->toMatch('/Fri 13 Nov<\/td>\s*<td class="text-end">2<\/td>\s*<td class="text-end">4<\/td>/')
        ->and($html)->toMatch('/\/prs<\/td>\s*<td class="text-end">3<\/td>/')
        ->and($html)->not->toContain('/secret-crawl')
        ->and($html)->toContain('Page tracking is switched off now');
});
