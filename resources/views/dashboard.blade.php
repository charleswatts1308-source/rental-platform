@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <h1>Dashboard</h1>
        <div class="alert alert-success">
            <h4>Welcome back!</h4>
            <p>You're successfully logged in to your Renters account.</p>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Your Rental Profile</h5>
                    </div>
                    <div class="card-body">
                        <p>Manage your rental properties and tenant information.</p>
                        <a href="{{ route('rentals.index') }}" class="btn btn-primary">View Rentals</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <p>Common tasks and resources.</p>
                        <a href="{{ route('rentals.create') }}" class="btn btn-success">Add New Rental</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
