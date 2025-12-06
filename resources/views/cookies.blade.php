@extends('layouts.app')

@section('title', 'Cookie Policy')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Header -->
            <div class="mb-5">
                <h1 class="display-5 mb-3">Cookie Policy</h1>
                <p class="text-muted">Last updated: December 2025</p>
            </div>

            <!-- No Tracking Cookies -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">We Don't Track You</h3>
                    <p>
                        <strong>Renters does not use any tracking cookies, advertising cookies, or third-party analytics cookies.</strong>
                    </p>
                    <p class="mb-0">
                        We don't track your browsing behaviour, build profiles about you,
                        or share your data with advertisers. When you leave our site, we're not following you around the internet.
                    </p>
                </div>
            </div>

            <!-- Essential Cookies Only -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Essential Cookies Only</h3>
                    <p>
                        We only use cookies that are strictly necessary for the website to function. These are:
                    </p>
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
                    <p class="mb-0">
                        These cookies are exempt from consent requirements under GDPR and the UK PECR regulations because
                        they are strictly necessary for the service you have requested (i.e., using our website while logged in).
                    </p>
                </div>
            </div>

            <!-- What We Don't Use -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">What We Don't Use</h3>
                    <ul class="mb-0">
                        <li><strong>Google Analytics</strong> - We don't track your page views or behaviour</li>
                        <li><strong>Facebook Pixel</strong> - We don't share data with social media platforms</li>
                        <li><strong>Advertising cookies</strong> - We don't serve targeted ads</li>
                        <li><strong>Third-party tracking</strong> - We don't allow other companies to track you on our site</li>
                        <li><strong>Preference cookies</strong> - We don't need to remember settings beyond your login</li>
                    </ul>
                </div>
            </div>

            <!-- No Cookie Banner Needed -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Why No Cookie Banner?</h3>
                    <p class="mb-0">
                        You may have noticed we don't show a cookie consent popup. That's because under GDPR and the
                        UK Privacy and Electronic Communications Regulations (PECR), consent is only required for non-essential
                        cookies. Since we only use strictly necessary cookies for the site to function, no consent banner is
                        legally required.
                    </p>
                </div>
            </div>

            <!-- Your Rights -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Managing Cookies</h3>
                    <p>
                        You can control and delete cookies through your browser settings. However, if you block our essential
                        cookies, you won't be able to log in or use the members-only features of the site.
                    </p>
                    <p class="mb-0">
                        For more information about your rights regarding your personal data, please see our
                        <a href="/privacy">Privacy Policy</a>.
                    </p>
                </div>
            </div>

            <!-- Contact -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Questions?</h3>
                    <p class="mb-0">
                        If you have any questions about our use of cookies, please contact us through the details provided
                        in our <a href="/privacy">Privacy Policy</a>.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
