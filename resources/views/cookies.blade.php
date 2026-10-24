@extends('layouts.app')

@section('title', 'Cookie Policy')

@section('content')
<h1 class="mb-4">Cookie Policy</h1>

        <h2 class="h5 mt-4">We Don't Track You</h2>
        <p>
            <strong>Renters does not use any tracking cookies, advertising cookies, or third-party analytics cookies.</strong>
        </p>
        <p>
            We don't track your browsing behaviour, build profiles about you,
            or share your data with advertisers. When you leave our site, we're not following you around the internet.
        </p>

        <h2 class="h5 mt-4">Essential Cookies Only</h2>
        <p>
            We only use cookies that are strictly necessary for the website to function. They are exempt from
            consent requirements under UK GDPR and PECR because they are strictly necessary
            for the service you have requested (i.e., using our website while logged in).
        </p>

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
        {{-- #48 — see privacy.blade.php. admin@renters.rent receives nothing;
             privacy@mg.renters.rent is delivered by the enquiry forwarder. --}}
        <p><strong>Email:</strong> <a href="mailto:privacy@mg.renters.rent">privacy@mg.renters.rent</a></p>
@endsection
