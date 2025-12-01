@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <h1 class="display-4 text-center mb-4">Renters.rent</h1>
            <p class="lead text-center mb-5">4.6 million renter households in England. £50+ billion per year. 3% of UK GDP.<br>Strength in numbers.</p>

            <!-- One Purpose -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">One Purpose</h2>
                    <p class="mb-0">To help you get a better deal from your landlord.</p>
                </div>
            </div>

            <!-- The Law Changed -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">The Law Changed</h2>
                    <p class="mb-0">On 1 May 2026, Section 21 "no-fault evictions" ends. The best news for renters in 36 years. The balance of power has shifted.</p>
                </div>
            </div>

            <!-- What Now -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">What Now?</h2>
                    <p class="mb-0">Join Renters.rent. Add your rental, your agent, your landlord. The bigger the database, the better you understand your position.</p>
                </div>
            </div>

            <!-- What You Get -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">What You Get</h2>
                    <p class="mb-0">Free access to guides on The Law, Know Your Landlord, Support Services and more.</p>
                </div>
            </div>

            <!-- Share This -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Share This</h2>
                    <p class="mb-0">The more renters who join, the stronger we all become.</p>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Register</a>
            </div>
        </div>
    </div>
</div>
@endsection
