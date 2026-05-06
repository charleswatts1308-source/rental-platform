<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Follow-up: repair issue</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Dear {{ $case->landlordContact->name ?: 'Sir or Madam' }},</p>

<p>I am writing to follow up on the repair issue I notified you about previously. To date I have not received an adequate response or an indication of when the works will be carried out.</p>

<p>
  <strong>Property:</strong> {{ $propertyAddress }}<br>
  <strong>Issue category:</strong> {{ $category->label }}<br>
  <strong>Severity:</strong> {{ ucfirst($case->severity->value) }}<br>
  <strong>Originally raised:</strong> {{ $case->opened_at->toFormattedDateString() }}
</p>

@if($caseMessage->tenant_statement)
<p><strong>Further information from me:</strong></p>
<blockquote style="border-left: 3px solid #ccc; padding-left: 12px; margin-left: 0; color: #444;">
  {{ $caseMessage->tenant_statement }}
</blockquote>
@endif

<p>As a reminder, section 11 of the Landlord and Tenant Act 1985 imposes a statutory duty on you to keep the structure and exterior in repair and the listed installations in proper working order. Continued lack of response increases the risk of the issue worsening and of legal liability.</p>

<p>Please respond to this email confirming when you intend to inspect and carry out the necessary works. Your reply will be linked to this case automatically.</p>

<p>Yours faithfully,<br>
{{ $tenantFirstName }}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. Replies are routed back to the tenant via the system; please reply to this email rather than emailing the tenant directly.
</p>

</body>
</html>
