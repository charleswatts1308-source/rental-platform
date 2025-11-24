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
                    A neutral platform connecting UK renters with information and each other
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

            <!-- Our Approach -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Our Approach</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h5>Neutral Platform</h5>
                            <p class="text-muted small">
                                We don't advocate for specific political positions. Instead, we provide information and connections
                                that empower renters to make their own informed decisions.
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h5>Information First</h5>
                            <p class="text-muted small">
                                Access to reliable, up-to-date information about rental law, rights, and market conditions
                                helps renters understand their situation better.
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h5>Connection Building</h5>
                            <p class="text-muted small">
                                Finding other renters who share your landlord or local area creates opportunities for
                                information sharing and mutual support.
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h5>Market Improvement</h5>
                            <p class="text-muted small">
                                When renters are well-informed and connected, it creates natural market pressures that
                                benefit everyone in the rental ecosystem.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Privacy First -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Privacy & Trust</h3>
                    <p>
                        We understand that renters share sensitive information about their housing, income, and personal circumstances.
                        That's why we've built our platform with privacy as a core principle:
                    </p>
                    <ul class="list-unstyled">
                        <li class="mb-2">✓ Essential cookies only - no tracking or analytics by default</li>
                        <li class="mb-2">✓ Minimal data collection - we only ask for what's necessary</li>
                        <li class="mb-2">✓ Transparent practices - clear information about how we handle data</li>
                        <li class="mb-0">✓ User control - you decide what information to share and with whom</li>
                    </ul>
                </div>
            </div>

            <!-- Contact -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="h4 mb-3">Get In Touch</h3>
                    <p>
                        Have questions about the platform or suggestions for improvement? We'd like to hear from you.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>General Inquiries</strong></p>
                            <p class="text-muted">hello@renters.rent</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Privacy Questions</strong></p>
                            <p class="text-muted">privacy@renters.rent</p>
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted small mb-0">
                        Renters is operated as a neutral platform to improve information sharing and connections
                        within the UK private rental sector.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
