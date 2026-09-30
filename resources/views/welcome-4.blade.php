@extends('layouts.app')

@section('title', 'Home')

@section('content')

{{-- DRAFT 30 Sep 2026 — Charlie's rewrite. The previous version (Renters /
     balance of power / three questions / Positive-Negative / "not the end of
     the line") is in git history on this file if any of it is wanted back. --}}

<div class="py-4">
    <h1 class="mb-4">Welcome to Renters</h1>

    <p class="lead mb-4">Does your home need repairs and replacements?</p>

    <p class="lead mb-4">Sign up to our Landlord Letter Service.</p>

    <p class="lead mb-2">If it's in writing and recorded you begin to take control.</p>

    {{-- STILL OPEN: the retaliation line. This is Charlie's latest wording. --}}
    <p class="lead mb-4">You have new rights now that encourage you to ask.</p>

    @guest
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Sign up and see what you can achieve</a>

        {{-- The homepage is a door, not the explanation — that was the 30 Sep
             decision: the process is too involved to compress into landing
             copy without overselling it. So the cautious reader needs a way to
             go and be convinced BEFORE being asked to register, rather than
             bouncing off a sign-up button. --}}
        <p class="mt-3 mb-0">
            <a href="{{ route('members.how-it-works') }}">Read how it works</a>
        </p>

        <p class="text-muted small mt-3 mb-0">
            Already with us? <a href="{{ route('login') }}">Log in</a>.
        </p>
    @else
        <a href="{{ route('cases.create') }}" class="btn btn-primary btn-lg">Raise a repair case</a>
    @endguest
</div>

@endsection
