@extends('layouts.app')

@section('title', 'Cookie Policy')

@section('content')
<h1 class="mb-4">Cookie Policy</h1>

<div class="card mb-4">
    <div class="card-body">
        <p class="text-muted"><strong>Last updated:</strong> December 2025</p>

        <h2 class="h5 mt-4">We Don't Track You</h2>
        <p>
            <strong>Renters does not use any tracking cookies, advertising cookies, or third-party analytics cookies.</strong>
        </p>
        <p>
            We don't track your browsing behaviour, build profiles about you,
            or share your data with advertisers. When you leave our site, we're not following you around the internet.
        </p>

        <h2 class="h5 mt-4">Essential Cookies Only</h2>
        <p>We only use cookies that are strictly necessary for the website to function. These are:</p>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Cookie</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>laravel_session</code></td>
                    <td>Keeps you logged in and maintains your session as you navigate the site</td>
                    <td>2 hours (or until you close your browser)</td>
                </tr>
                <tr>
                    <td><code>XSRF-TOKEN</code></td>
                    <td>Security token that protects against cross-site request forgery attacks</td>
                    <td>2 hours</td>
                </tr>
                <tr>
                    <td><code>remember_web_*</code></td>
                    <td>Only set if you tick "Remember me" when logging in</td>
                    <td>5 years</td>
                </tr>
            </tbody>
        </table>
        <p>
            These cookies are exempt from consent requirements under GDPR and the UK PECR regulations because
            they are strictly necessary for the service you have requested (i.e., using our website while logged in).
        </p>

        <h2 class="h5 mt-4">What We Don't Use</h2>
        <ul>
            <li><strong>Google Analytics</strong> - We don't track your page views or behaviour</li>
            <li><strong>Facebook Pixel</strong> - We don't share data with social media platforms</li>
            <li><strong>Advertising cookies</strong> - We don't serve targeted ads</li>
            <li><strong>Third-party tracking</strong> - We don't allow other companies to track you on our site</li>
            <li><strong>Preference cookies</strong> - We don't need to remember settings beyond your login</li>
        </ul>

        <h2 class="h5 mt-4">Why No Cookie Banner?</h2>
        <p>
            You may have noticed we don't show a cookie consent popup. That's because under GDPR and the
            UK Privacy and Electronic Communications Regulations (PECR), consent is only required for non-essential
            cookies. Since we only use strictly necessary cookies for the site to function, no consent banner is
            legally required.
        </p>

        <h2 class="h5 mt-4">Managing Cookies</h2>
        <p>
            You can control and delete cookies through your browser settings. However, if you block our essential
            cookies, you won't be able to log in or use the members-only features of the site.
        </p>
        <p>
            For more information about your rights regarding your personal data, please see our
            <a href="/privacy">Privacy Policy</a>.
        </p>

        <h2 class="h5 mt-4">Questions?</h2>
        <p>
            If you have any questions about our use of cookies, please contact us at:
        </p>
        <p><strong>Email:</strong> <a href="mailto:admin@renters.rent">admin@renters.rent</a></p>
    </div>
</div>
@endsection
