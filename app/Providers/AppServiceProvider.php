<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Testing env skips uncompromised() — the rule makes a live HTTPS call to
        // api.pwnedpasswords.com, which would slow the suite and fail Breeze's
        // stock fixtures (which post 'password'). min(8) still runs in tests so
        // structural strength is asserted; HIBP enforcement applies everywhere else.
        Password::defaults(function () {
            return $this->app->environment('testing')
                ? Password::min(8)
                : Password::min(8)->uncompromised();
        });
    }
}
