<?php

use App\Models\LandlordContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
});

it('fails loudly when DEV_TENANT_EMAIL is unset', function () {
    Config::set('dev.tenant_email', null);
    Config::set('dev.landlord_email', 'landlord@example.com');

    $this->artisan('dev:lifecycle')
        ->expectsOutputToContain('DEV_TENANT_EMAIL')
        ->assertExitCode(1);
});

it('fails loudly when DEV_LANDLORD_EMAIL is unset', function () {
    Config::set('dev.tenant_email', 'tenant@example.com');
    Config::set('dev.landlord_email', null);

    $this->artisan('dev:lifecycle')
        ->expectsOutputToContain('DEV_LANDLORD_EMAIL')
        ->assertExitCode(1);
});

it('seeds every demo case against the single configured tenant and landlord email', function () {
    Config::set('dev.tenant_email', 'demo-tenant@inbox.test');
    Config::set('dev.landlord_email', 'demo-landlord@inbox.test');

    $this->artisan('dev:lifecycle')->assertExitCode(0);

    $tenantEmails = User::where('is_admin', false)->pluck('email')->unique()->values()->all();
    expect($tenantEmails)->toBe(['demo-tenant@inbox.test']);

    $landlordEmails = LandlordContact::pluck('email')->unique()->values()->all();
    expect($landlordEmails)->toBe(['demo-landlord@inbox.test']);

    expect(User::where('email', 'like', '%@example.test')->exists())->toBeFalse();
    expect(LandlordContact::where('email', 'like', '%@example.test')->exists())->toBeFalse();
});
