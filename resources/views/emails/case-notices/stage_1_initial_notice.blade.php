<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Repair issue notification</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Dear {{ $case->landlordContact->name ?: 'Sir or Madam' }},</p>

<p>I am writing to formally notify you of a repair issue at the rental property below.</p>

<p>
  <strong>Property:</strong> {{ $propertyAddress }}<br>
  <strong>Issue category:</strong> {{ $category->label }}<br>
  <strong>Severity:</strong> {{ ucfirst($case->severity->value) }}<br>
  <strong>Date raised:</strong> {{ $case->opened_at->toFormattedDateString() }}
</p>

@if($caseMessage->tenant_statement)
<p><strong>My description of the issue:</strong></p>
<blockquote style="border-left: 3px solid #ccc; padding-left: 12px; margin-left: 0; color: #444;">
  {{ $caseMessage->tenant_statement }}
</blockquote>
@endif

<p>Under section 11 of the Landlord and Tenant Act 1985, you have a duty to keep in repair the structure and exterior of the property and to keep in proper working order the installations for the supply of water, gas, electricity and sanitation, and for space heating and water heating.</p>

<p>Please confirm receipt of this notice and let me know within a reasonable time when you intend to inspect and carry out the necessary works. You can reply to this email and your reply will be linked to this case automatically.</p>

<p>Yours faithfully,<br>
{{ $tenantFirstName }}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. Replies are routed back to the tenant via the system; please reply to this email rather than emailing the tenant directly. The tenant's contact details are kept private to protect against retaliation.
</p>

</body>
</html>
