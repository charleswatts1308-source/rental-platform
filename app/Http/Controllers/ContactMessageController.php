<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Support\ServiceAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{
    public function create()
    {
        return view('contact');
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $contactMessage = ContactMessage::create([
            'user_id' => Auth::id(),
            'subject' => $request->subject,
            'message' => $request->message,
        ]);

        $this->notify($contactMessage);

        return redirect()->route('contact.create')
            ->with('success', 'Your message has been sent. We will get back to you soon.');
    }

    /**
     * #30 — tell someone a message arrived.
     *
     * The row is written FIRST and the notification is best-effort after it.
     * Order matters: a mail failure must never cost us the message. The user
     * has been told we will get back to them, and the row is what makes that
     * recoverable even if this send fails.
     */
    private function notify(ContactMessage $contactMessage): void
    {
        $forwardTo = trim((string) config('enquiries.forward_to', ''));

        if ($forwardTo === '') {
            Log::warning('[LLCS] Contact Us message stored but not notified: '
                .'MAIL_ENQUIRY_FORWARD_TO is not set.', [
                    'contact_message_id' => $contactMessage->id,
                    'environment' => app()->environment(),
                ]);

            return;
        }

        Mail::to($forwardTo)->send(new ContactMessageReceived(
            $contactMessage->load('user'),
            ServiceAddress::contact(),
        ));
    }
}
