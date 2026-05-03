<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>The hold on your repair case has ended</title>
</head>
<body style="margin:0;padding:0;background-color:#f6f8fa;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f8fa;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;border:1px solid #e0e4e8;border-radius:6px;padding:32px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 16px 0;font-size:20px;color:#0f172a;">Your hold has ended</h1>
                            <p style="margin:0 0 16px 0;line-height:1.5;">
                                The pause you placed on case <strong>{{ $case->url_slug }}</strong> has expired.
                            </p>
                            <p style="margin:0 0 24px 0;line-height:1.5;">
                                Sign in to send the next letter, mark the case resolved, or step away.
                            </p>
                            <p style="margin:0 0 24px 0;">
                                <a href="{{ $caseUrl }}"
                                   style="display:inline-block;background-color:#047857;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:4px;font-weight:600;">
                                    Open this case
                                </a>
                            </p>
                            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
                                If the link doesn't work, paste this address into your browser:<br>
                                <span style="word-break:break-all;">{{ $caseUrl }}</span>
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0 0;font-size:12px;color:#94a3b8;">renters.rent — strength in numbers</p>
            </td>
        </tr>
    </table>
</body>
</html>
