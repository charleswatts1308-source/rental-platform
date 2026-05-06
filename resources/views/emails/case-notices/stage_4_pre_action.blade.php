<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pre-action letter: repair issue</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Dear {{ $case->landlordContact->name ?: 'Sir or Madam' }},</p>

<p><strong>This is a pre-action letter sent under the Pre-Action Protocol for Housing Conditions Claims (England).</strong> It is the final step before legal proceedings may be issued.</p>

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

<p>You have a statutory duty under section 11 of the Landlord and Tenant Act 1985 to keep in repair the structure and exterior of the property and to keep in proper working order the installations listed in that section. The duty has been breached by your continuing failure to act despite repeated written notice.</p>

<p>Under the Pre-Action Protocol I am required to set out the steps I have taken and the response I expect. I have given written notice of the issue, allowed reasonable time for inspection and works, and followed up. I now require:</p>

<ol>
  <li>A written response acknowledging the issue and confirming the works to be carried out.</li>
  <li>A clear timescale, with a fixed start date for inspection and a target completion date.</li>
  <li>Confirmation of who will attend and the expected duration of the works.</li>
</ol>

<p>If I do not receive a substantive response within a reasonable period from the date of this letter, I reserve the right to issue proceedings without further notice, to seek an order for specific performance and damages, and to apply for the costs of these proceedings against you. I may also refer the matter to the local authority's environmental health team and seek an improvement notice or hazard awareness notice under the Housing Act 2004.</p>

<p>Please reply to this email. Your reply will be linked to this case automatically and will form part of the documented record.</p>

<p>Yours faithfully,<br>
{{ $tenantFirstName }}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. Replies are routed back to the tenant via the system; please reply to this email rather than emailing the tenant directly. The full correspondence trail and any attached evidence form part of the documented record for any subsequent proceedings.
</p>

</body>
</html>
