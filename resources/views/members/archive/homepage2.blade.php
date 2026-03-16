@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h1 class="mb-4">Welcome to Renters.Rent</h1>

<p class="lead mb-4">If you live in the UK and rent your home then you should read this page.</p>

<div class="card mb-4">
    <div class="card-body">
        <p>This platform is here to provide renters in the UK with your own website devoted entirely to your interests.</p>
        <p class="mb-0">Membership is free and you will have a share in the website.</p>
    </div>
</div>

<div class="alert alert-info mb-4">
    <p>There are around 5 million renter households in the UK.</p>
    <p>You are paying £6 billion in rent monthly. That's big.</p>
    <p>This is the Private Rented Sector, the PRS, a huge sector, up there with agriculture, construction, telecoms.</p>
    <p class="mb-0">But as a group or sector you have zero serious organisation.</p>
</div>

<div class="alert alert-success mb-4">
    <p class="lead mb-0"><strong>This platform is here to fix that.</strong></p>
</div>

<div class="text-center mb-4">
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Sign Up</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <p class="mb-0">After you've joined, our info library becomes available, and we will share our many ideas for improving what you get for your rent.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5">Here's a little history</h2>
        <p>36 years ago (before the internet) renters were bound by Law to accept they had no security of tenure, allowing a landlord to end a rental agreement with no regard for the renter's needs. This was the infamous Section 21, and it meant that a wise renter did not push back against the landlord.</p>
        <p class="mb-0">Now the Renters Rights Act is here. Things are changing big-time. Now you can push back.</p>
    </div>
</div>

<div class="alert alert-info mb-4">
    <p>In 3 months time on 1 May 2026 the new Law becomes effective which removes Section 21 and also gives renters many more rights.</p>
    <p class="mb-0">This new freedom combined with the internet means you can now start to build your own network to reflect your economic contribution, and ensure maximum advantage from your new rights.</p>
</div>

<div class="alert alert-success mb-4">
    <p class="lead mb-0"><strong>Renters.Rent is the platform you need to make this happen.</strong></p>
</div>

<div class="card mb-4">
    <div class="card-body">
        <p>The advantages are just too numerous to mention here on the home page.</p>
        <p>I urge you to sign up and be counted.</p>
        <p class="mb-0">After you've joined, our info library becomes available, and we will share our many ideas for improving what you get for your rent.</p>
    </div>
</div>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Sign Up Now</a>
</div>
@endsection
