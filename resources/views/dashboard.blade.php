@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<h1 class="mb-4">Dashboard</h1>

<div class="row">
    <div class="col-12">
        <div class="alert alert-success">
            <h4>Welcome back!</h4>
            <p>You're logged in to your Renters account.</p>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Your Rental Profile</h5>
                    </div>
                    <div class="card-body">
                        <a href="{{ route('rentals.index') }}" class="btn btn-primary">View Rentals</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
