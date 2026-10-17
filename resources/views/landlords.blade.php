@extends('layouts.app')

@section('title', 'For Landlords')

@section('content')
<h1 class="mb-4">Information for landlords</h1>

<p class="lead">If you've arrived here after receiving an email about one of your properties,
this page provides some explanation.</p>

<hr class="my-4">

<h3 class="mb-3">The email you have received is genuine</h3>

<p>It was sent because one of your tenants used renters.rent to put a repair request in
writing. Our emails always come from an address ending <code>renters.rent</code>.</p>

<hr class="my-4">

{{--
    TRIAL WORDING — Charlie's decision, 2026-08-02. Deliberately gives no
    trader name or correspondence address: this is the one-off family
    trial, not a public launch. Both become required disclosures before
    the service opens to the public — see snag #42. Do not treat this
    wording as settled for launch.
--}}
<h3 class="mb-3">Who we are</h3>

<p>renters.rent is a new service developed to aid tenants and landlords in their joint
pursuit of rental property improvement.</p>

<p>We're not a claims company, not a solicitor, not a complaints body.</p>

<p>We're registered with the Information Commissioner's Office, the UK data protection
regulator, under registration reference <strong>Z229825X</strong>.</p>

<hr class="my-4">

<h3 class="mb-3">What renters.rent does</h3>

<p>It's a service that enables tenants to send repair requests as formal, dated correspondence.
The tenant writes the request; we format it, send it, and keep a simple record of
correspondence. </p>

<hr class="my-4">

<h3 class="mb-3">Why do we have your email address</h3>

<p>Your tenant gave it to us when they raised the request. We hope it is the "Service Address".</p>

<hr class="my-4">

<h3 class="mb-3">How to respond</h3>

<p><strong>Just reply to the email.</strong> Your reply goes back to your tenant and the
conversation can proceed.</p>

<p>You don't need an account and there's nothing to sign up for.</p>

<hr class="my-4">

<h3 class="mb-3">What we keep a record of</h3>

<p>We record what was sent, when it was sent, and any replies. Both you and your tenant can
rely on that record later if difficulties arise.</p>

<p>When the repair gets fixed, the tenant closes the case and the correspondence stops.</p>

<hr class="my-4">

<h3 class="mb-3">If you don't reply</h3>

<p>We will send reminders but that's it. We operate a correspondence service. </p>

<hr class="my-4">

<h3 class="mb-3">Checking this is your tenant</h3>

<p>The email includes the property address and your tenant's name. Please check these
against your own records. If they don't match, or you've received this in error,
<a href="{{ route('contact.create') }}">contact us</a> and we'll look into it promptly.</p>

<hr class="my-4">

<h3 class="mb-3">Your information</h3>

<p>We hold the email address your tenant gave us, the correspondence sent to it, and any
replies you send back. We use it only to pass messages between you and your tenant and to
keep the record described above.</p>

<p>Our <a href="/privacy">privacy notice</a> explains this in full, including how to ask
what we hold about you.</p>

<hr class="my-4">

<h3 class="mb-3">Repair responsibilities</h3>

<p>We don't give legal advice. For an authoritative summary of what
landlords and tenants are each responsible for, see the
<a href="https://www.gov.uk/private-renting/repairs" rel="noopener" target="_blank">guidance
on GOV.UK</a>.</p>

<hr class="my-4">

<h3 class="mb-3">Contact us</h3>

<p>If anything here doesn't look right please
<a href="{{ route('contact.create') }}">get in touch</a>.</p>
@endsection
