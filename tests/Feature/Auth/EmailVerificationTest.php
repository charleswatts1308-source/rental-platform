<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Snags #27 and #65.
 *
 * Charlie's acceptance test is two runs: register and verify in the SAME
 * browser, then register again and verify in a DIFFERENT one. Both must
 * end identically — verified, signed in, on the dashboard, same message.
 * The four tests below are those two runs plus the two ways a browser can
 * be "different": carrying no session, and carrying somebody else's.
 *
 * test_email_can_be_verified used to assert a redirect to
 * '?verified=1'. That assertion is repointed rather than dropped: the
 * query string was the defect (#65), so asserting it would now pin the
 * bug in place. Each test still asserts an exact destination AND the
 * message, which is a stronger assertion than the one it replaces.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    /** Route one: the link is clicked in the browser that registered. */
    public function test_email_can_be_verified_in_the_same_browser(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $response = $this->actingAs($user)->get($this->verificationUrl($user));

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', fn (string $status) => str_contains($status, 'thanks for registering'));
    }

    /**
     * Route two: the link is clicked in a browser with no session — the
     * Outlook-opens-Edge case. It must verify AND sign in, with no login
     * step, and land in exactly the same place as route one.
     */
    public function test_email_can_be_verified_in_a_browser_with_no_session(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $response = $this->get($this->verificationUrl($user));

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', fn (string $status) => str_contains($status, 'thanks for registering'));
    }

    /**
     * The 2 Aug 2026 prod failure: the browser held a session for someone
     * else and the request died at 403. The link's identity wins now.
     */
    public function test_a_link_opened_while_signed_in_as_someone_else_switches_to_the_links_owner(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($other)->get($this->verificationUrl($user));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_an_already_verified_link_signs_the_user_in_and_says_so(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $response = $this->get($this->verificationUrl($user));

        Event::assertNotDispatched(Verified::class);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', fn (string $status) => str_contains($status, 'already verified'));
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /** An unsigned or tampered URL is still refused outright. */
    public function test_an_unsigned_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get('/verify-email/'.$user->id.'/'.sha1($user->getEmailForVerification()))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    /** An expired link is refused — the 60-minute bound is real. */
    public function test_an_expired_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->get($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }
}
