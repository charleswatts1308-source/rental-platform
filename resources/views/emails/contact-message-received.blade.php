<div style="background: #f4f4f4; color: #222; border-left: 4px solid #888; padding: 12px 16px; margin-bottom: 20px; font-size: 13px;">
    <div><strong>Contact Us message</strong></div>
    <div><strong>From:</strong> {{ $contactMessage->user->name }} &lt;{{ $contactMessage->user->email }}&gt;</div>
    <div><strong>Received:</strong> {{ $contactMessage->created_at?->format('d M Y H:i') }}</div>
    <div style="margin-top: 8px; color: #666;">
        Replying to this email answers the sender directly. Your reply is NOT
        recorded on the platform — only their original message is.
    </div>
</div>

<p><strong>{{ $contactMessage->subject }}</strong></p>

<div>{!! nl2br(e($contactMessage->message)) !!}</div>
