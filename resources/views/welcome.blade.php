@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <h1 class="display-4 text-center mb-4">Renters.rent</h1>
            <p class="lead text-center mb-5">4.6 million renter households in England
                <br>Your rents are £52 billion per year
                <br>This is the PRS sector
                <br>3% of UK GDP
                <br>Huge strength in numbers</p>

            <!-- One Purpose -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">One Purpose</h2>
                    <p class="mb-0">To help you get a better deal from your landlord.</p>
                    <br>That may be to get things repaired, or negotiate a rent reduction, or even to buy your property</p>

                </div>
            </div>

            <!-- The Law Changed -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">The Law has Changed</h2>
                    <p class="mb-0">On 1 May 2026, Section 21 "no-fault evictions" ends. The best news for renters in 36 years. The balance of power has seriously shifted in your favour.</p>
                </div>
            </div>

            <!-- What Now -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">What Now?</h2>
                    <p class="mb-0">Join Renters.rent and become a member. Enter details of your rented home, your agent and landlord.
                        The more that join, the more leverage you will all have.</p>
                </div>
            </div>

            <!-- What You Get -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">What You Get</h2>
                    <p class="mb-0">The bargaining power that comes from belonging to an expanding membership.</p>
                    <br>
                    <p class="mb-0">Access to our free members pages covering all important topics,
                        particularly how to contact your landlord formally and start negotiations. </p>
                    <br>
                    <p class="mb-0">Access to our paid service to discover your landlord's situation just like they do to you.</p>
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
