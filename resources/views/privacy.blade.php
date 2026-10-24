@extends('layouts.app')

@section('title', 'Privacy Policy')

@section('content')
<h1 class="mb-4">Privacy Policy</h1>

{{-- Rewritten 24 Oct 2026 to cover what the service actually holds,
     including landlords' details supplied by tenants, plus the items UK
     GDPR expects (lawful basis, processors, retention, ICO complaint).
     Controller name and postal address are still withheld under the
     trial-wording decision - see landlords.blade.php and snag #42. --}}

<h2 class="h5 mt-4">1. Who we are</h2>
<p>renters.rent helps tenants in England &amp; Wales send repair requests to their landlord as formal,
dated correspondence. We are the data controller for the personal information described here, and we are
registered with the Information Commissioner's Office under reference <strong>Z229825X</strong>.</p>

<h2 class="h5 mt-4">2. Who this policy covers</h2>
<ul>
    <li><strong>Tenants</strong> who register and use the service</li>
    <li><strong>Landlords and letting agents</strong> whose details a tenant gives us so that we can send them a repair request</li>
    <li><strong>Anyone</strong> who visits the website or contacts us</li>
</ul>

<h2 class="h5 mt-4">3. What we collect</h2>
<p><strong>If you are a tenant:</strong></p>
<ul>
    <li>Your name, email address and password (stored securely, never in readable form)</li>
    <li>The address of the property you rent</li>
    <li>The name and contact details of your landlord or letting agent</li>
    <li>The details of your repair requests, and any photos or documents you upload</li>
    <li>The letters we send on your behalf, and the replies your landlord sends back</li>
</ul>
<p><strong>If you are a landlord or letting agent:</strong></p>
<ul>
    <li>Your name and email address, as given to us by your tenant</li>
    <li>The address of the property concerned</li>
    <li>Any replies you send, and anything you attach to them</li>
</ul>
<p><strong>Everyone:</strong></p>
<ul>
    <li>Messages you send us through the contact form or by email</li>
    <li>Technical information when you visit the site: your IP address, browser type and the pages you view</li>
</ul>

<h2 class="h5 mt-4">4. How we use it, and our legal basis</h2>
<ul>
    <li><strong>Tenants:</strong> to provide the service you signed up for &mdash; sending your letters, passing on
        replies and keeping a record of the correspondence. Legal basis: performing our contract with you.</li>
    <li><strong>Landlords and agents:</strong> to deliver your tenant's repair request, pass your replies back to them
        and keep an accurate record of the correspondence. Legal basis: legitimate interests &mdash; the tenant's
        interest in raising repairs in writing, and ours in providing a reliable record.</li>
    <li><strong>Technical information:</strong> to keep the site secure, spot misuse and understand how the site is
        used. Legal basis: legitimate interests.</li>
    <li><strong>Where the law requires it:</strong> for example, responding to a lawful request from a court or
        regulator. Legal basis: legal obligation.</li>
</ul>
<p>We do not sell personal information, and we do not use it for advertising.</p>

<h2 class="h5 mt-4">5. Who we share it with</h2>
<ul>
    <li><strong>The other party to a repair case:</strong> your landlord or agent receives the letters a tenant
        sends; the tenant receives the replies the landlord or agent sends.</li>
    <li><strong>Mailgun</strong>, which delivers and receives our email, using its European data centre.</li>
    <li><strong>Our web hosting provider</strong>, which stores the website and its data in the UK.</li>
    <li><strong>Google Fonts and jsDelivr</strong>, which supply the site's fonts and styling. When a page loads,
        your browser fetches these from their servers, which see your IP address. Google may process this outside
        the UK.</li>
    <li><strong>Courts, regulators or the police</strong>, where we are legally required to.</li>
</ul>

<h2 class="h5 mt-4">6. How long we keep it</h2>
<ul>
    <li><strong>Account details:</strong> while your account is open.</li>
    <li><strong>Repair cases, letters and replies:</strong> as long as they may be needed as a record of the
        correspondence, including for any legal claim about the repair.</li>
    <li><strong>Contact messages:</strong> as long as needed to deal with your enquiry.</li>
    <li><strong>Technical information:</strong> as long as needed to keep the site secure and understand how it is used.</li>
</ul>

<h2 class="h5 mt-4">7. Keeping it secure</h2>
<p>We use appropriate technical and organisational measures to protect personal information against
unauthorised access, alteration, disclosure or destruction.</p>

<h2 class="h5 mt-4">8. Your rights</h2>
<p>Under UK data protection law you have the right to:</p>
<ul>
    <li>ask for a copy of the personal information we hold about you</li>
    <li>have inaccurate information corrected</li>
    <li>ask us to delete your information</li>
    <li>ask us to restrict how we use it, or object to our using it</li>
    <li>receive your information in a portable format</li>
</ul>
<p>These rights apply to landlords and agents as well as tenants. To use any of them, email us at the address
below. We will respond within one month.</p>
<p>If you are unhappy with how we have handled your information, you can complain to the Information
Commissioner's Office at <a href="https://ico.org.uk/make-a-complaint/" rel="noopener" target="_blank">ico.org.uk</a>
or on 0303 123 1113.</p>

<h2 class="h5 mt-4">9. Cookies</h2>
<p>We use only the cookies the site needs to work. Our <a href="/cookies">Cookie Policy</a> explains them.</p>

<h2 class="h5 mt-4">10. Contact us</h2>
<p>For any question about this policy or your information:</p>
{{-- #48 — admin@renters.rent could not receive mail (no mailbox behind
     the apex MX), so a data subject exercising their rights got
     silence and we never learned they tried. privacy@mg.renters.rent
     is delivered by the stage 1 enquiry forwarder and was proven
     live on 19 Sep 2026. --}}
<p><strong>Email:</strong> <a href="mailto:privacy@mg.renters.rent">privacy@mg.renters.rent</a></p>

<h2 class="h5 mt-4">11. Changes to this policy</h2>
<p>We may update this policy from time to time. Any changes will be posted on this page.</p>
@endsection
