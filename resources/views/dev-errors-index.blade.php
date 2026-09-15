@extends('layouts.app')

@section('title', 'Error pages')

@section('content')
<div class="container py-4">
    <h1 class="mb-3">Error pages</h1>

    {{-- #57. Three of the four error pages shipped without anyone seeing
         them, because each is awkward to trigger honestly. This page exists
         so "we built graceful error pages" can be checked rather than
         assumed. --}}
    <p class="lead">Each link renders the real page with its real status code.</p>

    <ul>
        <li><a href="{{ route('dev.errors', '404') }}">404</a> — page not found. Reachable normally by visiting any bad URL.</li>
        <li><a href="{{ route('dev.errors', '413') }}">413</a> — upload too large. Hard to reach for real: the create form now refuses an over-budget selection before it submits.</li>
        <li><a href="{{ route('dev.errors', '419') }}">419</a> — session expired. Reachable for real by leaving a form open past the session lifetime, or deleting the session cookie and submitting.</li>
        <li><a href="{{ route('dev.errors', '500') }}">500</a> — something went wrong. No natural trigger.</li>
    </ul>

    <p class="text-muted small mb-0">
        This page is gated to local, staging and preprod. Production refuses it.
    </p>
</div>
@endsection
