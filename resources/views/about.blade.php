@extends('layouts.app')

@section('title', 'About Us')

@section('content')
<h1 class="mb-4">About renters.rent</h1>

<p class="lead">renters.rent is a repair-notice service. It does one thing: it sends
your landlord a proper, formal notice that a repair is needed, gives them a fair
period to respond, and keeps a dated record of what follows.</p>

<hr class="my-4">

<h3 class="mb-3">What it does</h3>

<p>You tell us what needs fixing and who your landlord or agent is. We write the notice,
send it, and start the clock. If your landlord replies, that's recorded. If they go quiet,
the record moves forward through defined stages, each one dated, without you having to
chase anything.</p>

<p>However it ends, you're left holding the record of it.</p>

<hr class="my-4">

<h3 class="mb-3">What it doesn't do</h3>

<p>It doesn't chase on your behalf, give legal advice, or take sides in a dispute. It runs
a clear procedure and documents it. That's the whole service — and knowing where it stops
matters as much as knowing what it covers.</p>

<hr class="my-4">

<h3 class="mb-3">Why it works</h3>

<p>The idea is simple. A repair asked for properly, in writing, on a timescale — with every
step dated — is harder to ignore than a phone call or a text.</p>

<p>If your landlord acts, good. That's the outcome everyone wants. If they don't, you have
a documented trail showing you followed the correct steps and gave fair notice — and that
trail is worth having.</p>

<hr class="my-4">

<p><a href="{{ route('members.how-it-works') }}">How It Works</a> sets out the process step
by step.</p>

@guest
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-2">Sign up</a>
@else
    <a href="{{ route('cases.create') }}" class="btn btn-primary btn-lg mt-2">Raise a repair case</a>
@endguest

@endsection
