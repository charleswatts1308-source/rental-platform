<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        // The form always renders (no coming-soon page); the gate is in store().
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_when_open_to_all(): void
    {
        config(['app.registration_open_to_all' => true]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_open_to_all_ignores_the_allowlist(): void
    {
        // The boolean is the only on/off control: open-to-all admits anyone
        // even when the allowlist is empty.
        config([
            'app.registration_open_to_all' => true,
            'app.registration_allowlist' => '',
        ]);

        $this->post('/register', [
            'name' => 'Anyone',
            'email' => 'stranger@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'stranger@example.com']);
    }

    public function test_beta_blocks_a_non_allowlisted_email(): void
    {
        config([
            'app.registration_open_to_all' => false,
            'app.registration_allowlist' => 'invited@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Uninvited',
            'email' => 'stranger@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);
    }

    public function test_beta_allows_an_allowlisted_email_case_insensitively(): void
    {
        config([
            'app.registration_open_to_all' => false,
            // Mixed case + surrounding spaces in the config; submitted lowercase.
            'app.registration_allowlist' => ' Invited@Example.com , other@example.com ',
        ]);

        $this->post('/register', [
            'name' => 'Invited Guest',
            'email' => 'invited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
    }

    public function test_registration_normalises_a_capitalised_email(): void
    {
        // The SUBMITTED side. The test above varies case in the CONFIG only
        // and submits lowercase, so this path was untested: Breeze's
        // `lowercase` rule used to REJECT a capitalised address rather than
        // tidy it. Capitalising your own email is an ordinary thing to do,
        // and the resulting "must be lowercase" error reads as nonsense.
        config([
            'app.registration_open_to_all' => false,
            'app.registration_allowlist' => 'invited@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Invited Guest',
            'email' => '  Invited@Example.COM  ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // Stored lowercased — the allowlist gate, landlord contacts and
        // inbound reply matching all compare against lowercase.
        $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
    }

    public function test_empty_allowlist_in_beta_admits_nobody_but_stays_beta(): void
    {
        // KEY PROPERTY: emptying the allowlist must NOT open or close the
        // beta. An empty list in beta simply means "nobody invited yet" —
        // every registration is refused, and the site stays in beta (only
        // the boolean could flip it open).
        config([
            'app.registration_open_to_all' => false,
            'app.registration_allowlist' => '',
        ]);

        $response = $this->post('/register', [
            'name' => 'Nobody',
            'email' => 'nobody@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nobody@example.com']);

        // State unchanged by the empty list: the boolean still reads false.
        $this->assertFalse(config('app.registration_open_to_all'));
    }
}
