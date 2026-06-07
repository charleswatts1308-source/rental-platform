<?php

use App\Enums\CaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('the TenantActionRequired enum case is gone', function () {
    expect(defined('App\Enums\CaseStatus::TenantActionRequired'))->toBeFalse();
    expect(in_array('tenant_action_required', array_map(fn ($c) => $c->value, CaseStatus::cases()), true))->toBeFalse();
});

it('the cases:sweep-holds command class no longer exists', function () {
    expect(@class_exists('App\\Console\\Commands\\SweepHolds', false))->toBeFalse();
});

it('the cases:sweep-dormancy command class no longer exists', function () {
    expect(@class_exists("App\Console\Commands\SweepDormancy", false))->toBeFalse();
});

it('the DormancyReminder mailable no longer exists', function () {
    expect(@class_exists("App\Mail\Notifications\DormancyReminder", false))->toBeFalse();
});

it('the HoldExpired mailable no longer exists', function () {
    expect(@class_exists("App\Mail\Notifications\HoldExpired", false))->toBeFalse();
});

it('the LandlordReplyReceived mailable no longer exists', function () {
    expect(@class_exists("App\Mail\Notifications\LandlordReplyReceived", false))->toBeFalse();
});

it('the send-next route is gone', function () {
    expect(Route::getRoutes()->getByName('cases.send-next'))->toBeNull();
});

it('the re-engage route is gone', function () {
    expect(Route::getRoutes()->getByName('cases.re-engage'))->toBeNull();
});

it('the reply route exists in its place', function () {
    expect(Route::getRoutes()->getByName('cases.reply'))->not->toBeNull();
});

it('the magic-link.consume route exists', function () {
    expect(Route::getRoutes()->getByName('magic-link.consume'))->not->toBeNull();
});

it('the cases.preview + cases.confirm routes exist', function () {
    expect(Route::getRoutes()->getByName('cases.preview'))->not->toBeNull();
    expect(Route::getRoutes()->getByName('cases.confirm'))->not->toBeNull();
});

it('the cases.description column exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('cases', 'description'))->toBeTrue();
});

it('the cases.dormant_at column exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('cases', 'dormant_at'))->toBeTrue();
});

it('the magic_login_tokens table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('magic_login_tokens'))->toBeTrue();
});

it('settings include dormancy.revival_days and hold.max_days', function () {
    expect((int) \App\Models\Setting::get('dormancy.revival_days', 0))->toBe(90);
    expect((int) \App\Models\Setting::get('hold.max_days', 0))->toBe(60);
});
