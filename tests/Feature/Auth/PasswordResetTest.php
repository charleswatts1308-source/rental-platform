<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    /*
     * #75 — the reset routes used to sit inside the `guest` middleware group,
     * so a signed-in visitor was redirected to the dashboard and never saw the
     * form. The victim is a tenant permanently signed in on a phone who has
     * forgotten the password: the profile page then demands the very password
     * they are trying to replace, and the loop never closes.
     */

    public function test_a_signed_in_visitor_still_reaches_the_reset_form(): void
    {
        Notification::fake();

        $signedIn = User::factory()->create();
        $resetting = User::factory()->create();

        $this->post('/forgot-password', ['email' => $resetting->email]);

        Notification::assertSentTo($resetting, ResetPassword::class, function ($notification) use ($signedIn) {
            $response = $this->actingAs($signedIn)->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_reaching_the_reset_form_ends_the_existing_session(): void
    {
        Notification::fake();

        $signedIn = User::factory()->create();
        $resetting = User::factory()->create();

        $this->post('/forgot-password', ['email' => $resetting->email]);

        Notification::assertSentTo($resetting, ResetPassword::class, function ($notification) use ($signedIn) {
            $this->actingAs($signedIn)->get('/reset-password/'.$notification->token);

            // Clicking a reset link is an unambiguous "I want new credentials",
            // so the old session goes. It also removes the confusing half-state
            // where you hold one account's session while resetting another's.
            $this->assertGuest();

            return true;
        });
    }

    public function test_a_signed_in_visitor_can_complete_a_reset_for_another_account(): void
    {
        Notification::fake();

        $signedIn = User::factory()->create();
        $resetting = User::factory()->create();

        $this->post('/forgot-password', ['email' => $resetting->email]);

        Notification::assertSentTo($resetting, ResetPassword::class, function ($notification) use ($signedIn, $resetting) {
            $response = $this->actingAs($signedIn)->post('/reset-password', [
                'token' => $notification->token,
                'email' => $resetting->email,
                'password' => 'new-password-here',
                'password_confirmation' => 'new-password-here',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertGuest();

            return true;
        });
    }
}
