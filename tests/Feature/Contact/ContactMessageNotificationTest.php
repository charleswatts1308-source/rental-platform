<?php

use App\Mail\ContactMessageReceived;
use App\Mail\ContactReply;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.mailgun.inbound_domain', 'mg.renters.rent');
    Config::set('enquiries.forward_to', 'private@example.com');
    Mail::fake();
});

/*
 * #30 — Contact Us used to write a row and do nothing else. The only way to
 * learn a message existed was to log into admin and look, while the user had
 * been told "we will get back to you soon". These pin the cheap fix: a
 * notification whose Reply-To is the user, so an ordinary mail client answers
 * them. NOT the August rebuild, which was a real threaded conversation.
 */

it('emails the configured mailbox when a contact message is submitted', function () {
    $user = User::factory()->create(['email' => 'tenant@example.com', 'name' => 'A Tenant']);

    $this->actingAs($user)->post('/contact', [
        'subject' => 'Is this service free?',
        'message' => 'I would like to know before I raise a case.',
    ])->assertRedirect(route('contact.create'));

    Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail) {
        $envelope = $mail->envelope();

        return $mail->hasTo('private@example.com')
            && $envelope->from->address === 'info@mg.renters.rent'
            && $envelope->replyTo[0]->address === 'tenant@example.com'
            && str_contains($envelope->subject, 'Is this service free?');
    });
});

it('still stores the message when no forward address is configured', function () {
    Config::set('enquiries.forward_to', null);

    $user = User::factory()->create();

    $this->actingAs($user)->post('/contact', [
        'subject' => 'Hello',
        'message' => 'Anyone there?',
    ])->assertRedirect(route('contact.create'));

    // The row is what makes the on-screen promise recoverable. A mail
    // failure must never cost us the message.
    expect(ContactMessage::count())->toBe(1);
    Mail::assertNothingSent();
});

it('sends the admin reply from an address that can actually receive', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['email' => 'tenant@example.com']);

    $message = ContactMessage::create([
        'user_id' => $user->id,
        'subject' => 'A question',
        'message' => 'Some text',
    ]);

    $this->actingAs($admin)
        ->post('/admin/contact-messages/'.$message->id.'/reply', [
            'admin_reply' => 'Here is your answer.',
        ]);

    Mail::assertSent(ContactReply::class, function (ContactReply $mail) {
        // Was noreply@renters.rent — an apex address with no mailbox, so a
        // user hitting Reply wrote into nothing and nobody found out.
        return $mail->envelope()->from->address === 'info@mg.renters.rent';
    });
});
