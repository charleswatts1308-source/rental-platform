<!DOCTYPE html>
<html>
<body>
<p>Dear {{ $contactMessage->user->name }},</p>

<p>Thank you for your message regarding: <strong>{{ $contactMessage->subject }}</strong></p>

<hr>

<p>{!! nl2br(e($contactMessage->admin_reply)) !!}</p>

<hr>

<p>Your original message:<br>
{!! nl2br(e($contactMessage->message)) !!}</p>

<p>Best regards,<br>
Renters</p>
</body>
</html>
