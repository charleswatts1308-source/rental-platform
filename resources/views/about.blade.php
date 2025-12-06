@extends('layouts.app')

@section('title', 'About Us')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Header -->
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">About Renters</h1>
                <p class="lead text-muted">
                    This platform connects UK renters with information about their shared landlords and each other
                </p>
            </div>

            <!-- Mission -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Our Mission</h3>
                    <p>
                        We believe that when renters have access to good information and can connect with others in similar situations,
                        the entire rental market works more effectively for everyone. As a neutral platform, we provide the tools and
                        connections that help individual renters make informed decisions about their housing.
                    </p>
                </div>
            </div>

            <!-- The Problem -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">The Challenge We Address</h3>
                    <p>
                        Right now, many of the UK's 4.6 million renter households are effectively powerless when dealing with rental issues.
                        As individual renters facing landlords, agents, and a complex legal system, they often have little leverage and
                        limited recourse when problems arise. Despite being part of a £52 billion sector that represents 3% of the UK economy,
                        individual renters are isolated and lack the collective influence their numbers should provide.
                    </p>
                    <p class="mb-0">
                        Our platform helps renters understand they're part of something much bigger and provides the information and
                        connections that can help rebalance these dynamics naturally.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
