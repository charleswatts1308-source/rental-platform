@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h1 class="mb-4">Welcome to Renters</h1>

<p class="lead mb-4">Here are some facts about our UK property rental market</p>

<div class="alert alert-info mb-4">
    <p>4.6 million renter households in the UK pay £70 billion in rent annually - that's 3% of GDP, more that the agriculture sector.</p>
    <p>Combined, you are a big economic force</p>
    <p>Like it or not, you live in a network world</p>
    <p >Renters have no network of their own</p>
    <p >On 1 May this year, the Renters Rights Act abolishes "Section 21 No Fault Evictions"</p>
    <p class="mb-0">You will have a long list of new "rights"</p>

</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5">Here's the idea:</h2>
        <p class="mb-0">What if you could see who else rents from your landlord? Where does the £52 billion actually go? What if thousands of you could demonstrate your collective significance to lenders, insurers, suppliers, and policymakers?</p>
    </div>
</div>

<div class="alert alert-info mb-4">
    <p>Nobody knows what the critical mass number is.</p>

    <p>Is it 10,000 registrations? 50,000? 100,000?</p>

    <p>4,217 renters have registered so far. That's definitely not enough.</p>

    <p class="mb-0">100,000 probably is.</p>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5">This is the experiment:</h2>
        <p>Register. Get counted. Join fellow renters to find out what's actually possible.</p>

        <p class="mb-0">If it works, you'll have access to information that's currently invisible and economic power that's currently unused. If it doesn't, you've lost 30 seconds.</p>
    </div>
</div>

<div class="alert alert-success mb-4">
    <div class="text-center">
        <h2 class="display-6 mb-3">4,217</h2>
        <p class="lead mb-0">Just be counted</p>
    </div>
</div>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Register</a>
</div>
@endsection
