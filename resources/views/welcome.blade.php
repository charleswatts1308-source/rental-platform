@extends('layouts.app')

@section('title', 'Home')

@section('content')
<!-- Hero Section -->
<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3">You're Part of Something Bigger</h1>
                <p class="lead mb-4">
                    Right now, you might feel like just one person dealing with rental issues alone. But you're actually part of 4.6 million households in the UK's Private Rental Sector - representing 3% of the entire UK economy. That's £52 billion annually. When renters have good information and can connect with others in similar situations, the whole rental market works better for everyone.
                </p>
                @guest
                    <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">Get Started</a>
                    <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg">Sign In</a>
                @else
                    <a href="{{ route('dashboard') }}" class="btn btn-light btn-lg">Go to Dashboard</a>
                @endguest
            </div>
            <div class="col-lg-4 text-center">
                <div class="bg-light text-primary rounded p-4">
                    <h2 class="h1 mb-0">4.6M</h2>
                    <p class="mb-0">UK Rental Households</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="h1 mb-3">Resources for UK Renters</h2>
                <p class="lead text-muted">Information, connections, and tools to help you navigate the rental market</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-primary text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                        <h5 class="card-title">Connect with Renters</h5>
                        <p class="card-text">Find other renters who share your landlord or local area. Share experiences and information that helps everyone make better decisions.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-success text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-shield-alt fa-lg"></i>
                        </div>
                        <h5 class="card-title">Know Your Rights</h5>
                        <p class="card-text">Access up-to-date information about UK rental law, the Renters' Rights Act, and your legal protections.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-warning text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-chart-line fa-lg"></i>
                        </div>
                        <h5 class="card-title">Build Connections</h5>
                        <p class="card-text">When renters connect and share information, everyone benefits from better knowledge and shared experiences.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="bg-light py-5">
    <div class="container">
        <div class="row text-center mb-4">
            <div class="col-12">
                <h3>The Numbers Tell a Story</h3>
                <p class="text-muted">Understanding the scale and structure of the UK rental market</p>
            </div>
        </div>
        <div class="row text-center">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="h2 text-primary fw-bold">4.6M</div>
                <p class="text-muted">Renter Households</p>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="h2 text-success fw-bold">~400K</div>
                <p class="text-muted">Individual Landlords</p>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="h2 text-warning fw-bold">~15K</div>
                <p class="text-muted">Letting Agents</p>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="h2 text-primary fw-bold">£52B</div>
                <p class="text-muted">Annual Market Value</p>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <p class="text-muted">
                    <small>That's more than <strong>10 renters for every landlord</strong> - yet individual renters often feel isolated when dealing with rental issues.
                    Nearly half (43%) of landlords own just one property, while the average landlord has 8.6 properties.
                    When renters have good information and connections, the entire market works more effectively.</small>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
@guest
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="h1 mb-3">Ready to Get Started?</h2>
                <p class="lead text-muted mb-4">
                    Join thousands of UK renters building collective strength in the private rental sector.
                </p>
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg me-3">Create Your Account</a>
                <a href="#" class="btn btn-outline-primary btn-lg">Learn More</a>
            </div>
        </div>
    </div>
</section>
@endguest
@endsection

@section('scripts')
<!-- Font Awesome for icons -->
<script src="https://kit.fontawesome.com/your-font-awesome-kit.js" crossorigin="anonymous"></script>
@endsection
