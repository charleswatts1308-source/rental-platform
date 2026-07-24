@extends('layouts.app')

@section('title', 'Home')

@section('content')

{{-- Opening statement. --}}
<div class="py-4">
    <h1 class="mb-4">Renters</h1>

    <p class="lead mb-1">The recent Renters' Rights Act has changed the balance of power.</p>
    <p class="lead">In your favour.</p>
</div>

<hr class="my-4">

{{-- The three questions — the reasons someone is on this page at all. --}}
<div class="py-2">
    <p class="lead mb-2">You want repairs done?</p>
    <p class="lead mb-2">You want improvements?</p>
    <p class="lead mb-0">You want to talk formally to your landlord?</p>
</div>

{{-- The turn. --}}
<div class="py-4">
    <h2 class="h3 mb-3">Use Renters</h2>

    <ul class="lead">
        <li class="mb-2">We will do all letters for you.</li>
        <li class="mb-2">We follow the statutory notice process and all required compliance protocols.</li>
        <li>We will ensure a useful outcome — whatever that may be.</li>
    </ul>

    {{-- Early CTA: this is the point of conviction, so offer the way in here
         rather than making a persuaded reader scroll to the bottom for it. --}}
    @guest
        <a href="{{ route('register') }}" class="btn btn-primary mt-3">Sign up</a>
    @else
        <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">Go to your dashboard</a>
    @endguest
</div>

<hr class="my-4">

{{-- Outcomes, stated honestly — the negative column is the more important of
     the two, and is deliberately not softened. --}}
<div class="row g-4 py-2">
    <div class="col-md-6">
        <h2 class="h5">Positive</h2>
        <p class="mb-0">Your landlord may engage usefully and do the work.</p>
    </div>

    <div class="col-md-6">
        <h2 class="h5">Negative</h2>
        <p>Your landlord may engage and refuse the work, either unwilling or unable to afford it.</p>
        <p class="mb-0">Your landlord may not engage.</p>
    </div>
</div>

<hr class="my-4">

{{-- The close. This is the real promise of the product, so it carries the
     page's final emphasis and the main call to action. --}}
<div class="py-2 pb-4">
    <p class="lead mb-2">This is not the end of the line.</p>
    <p class="lead fw-semibold mb-4">Your Rental Contract is your power here. Use it. We'll show you how.</p>

    @guest
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Sign up</a>
        <p class="text-muted small mt-3 mb-0">
            Already with us? <a href="{{ route('login') }}">Log in</a>.
        </p>
    @else
        <a href="{{ route('cases.create') }}" class="btn btn-primary btn-lg">Raise a repair case</a>
    @endguest
</div>

@endsection
