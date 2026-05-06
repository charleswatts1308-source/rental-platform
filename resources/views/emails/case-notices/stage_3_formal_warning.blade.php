<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Formal warning: repair issue</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Dear {{ $case->landlordContact->name ?: 'Sir or Madam' }},</p>

<p><strong>This is a formal warning concerning your continuing failure to attend to a repair issue at the property below.</strong></p>

<p>
  <strong>Property:</strong> {{ $propertyAddress }}<br>
  <strong>Issue category:</strong> {{ $category->label }}<br>
  <strong>Severity:</strong> {{ ucfirst($case->severity->value) }}<br>
  <strong>Originally raised:</strong> {{ $case->opened_at->toFormattedDateString() }}<br>
  <strong>Time elapsed since first notice:</strong> {{ $case->opened_at->diffInDays(now()) }} days
</p>

@if($caseMessage->tenant_statement)
<p><strong>My current position:</strong></p>
<blockquote style="border-left: 3px solid #ccc; padding-left: 12px; margin-left: 0; color: #444;">
  {{ $caseMessage->tenant_statement }}
</blockquote>
@endif

<p>Under section 11 of the Landlord and Tenant Act 1985 you remain under a continuing statutory duty to repair. The Pre-Action Protocol for Housing Conditions Claims (England) sets out the steps a tenant is expected to take before issuing court proceedings, and this letter is one of those steps.</p>

<p>If you do not respond with a clear plan and timescale for the works, I will move to the formal pre-action stage and may instruct legal advice or refer the matter to the relevant local authority's environmental health team. I urge you to engage with this notice now rather than allow the matter to escalate further.</p>

<p>Yours faithfully,<br>
{{ $tenantFirstName }}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. Replies are routed back to the tenant via the system; please reply to this email rather than emailing the tenant directly.
</p>

</body>
</html>
