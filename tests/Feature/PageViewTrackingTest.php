<?php

use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
 * Snag #92 — page tracking must hold only what visitor counting needs.
 * Cookies include session and remember-me cookies (credentials); query
 * strings carry password-reset tokens and signed verification parameters.
 */

it('has no cookies column on page_views', function () {
    expect(Schema::hasColumn('page_views', 'cookies'))->toBeFalse();
});

it('records a signed-in user\'s visit without cookies or query string', function () {
    config(['app.track_page_views' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withCookie('remember_web_abc', 'secret-remember-value')
        ->get('/prs?token=reset-secret&email=someone%40example.test')
        ->assertOk();

    $view = PageView::sole();
    $row = (array) \DB::table('page_views')->where('page_view_id', $view->page_view_id)->first();

    expect($view->page_name)->toEndWith('/prs')
        ->and($view->page_name)->not->toContain('?')
        ->and(json_encode($row))->not->toContain('secret-remember-value')
        ->and(json_encode($row))->not->toContain('reset-secret')
        ->and(json_encode($row))->not->toContain('someone')
        ->and(array_keys($row))->toEqualCanonicalizing(
            ['page_view_id', 'view_date_time', 'page_name', 'ip_address', 'user_agent']
        );
});

it('records nothing when tracking is off', function () {
    config(['app.track_page_views' => false]);

    $this->get('/prs')->assertOk();

    expect(PageView::count())->toBe(0);
});

it('does not show a cookies column on the admin Page views screen', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)
        ->get('/admin/page-views')
        ->assertOk()
        ->assertDontSee('<th>Cookies</th>', false);
});
