@extends('layouts.app')

@section('title', 'Admin - Users')

@section('content')
<h1 class="mb-4">Users</h1>

<div class="alert alert-info mb-4">
    <p class="mb-1"><strong>Total Verified:</strong> {{ $totalVerified }}</p>
    <p class="mb-0"><strong>Total Non-Verified:</strong> {{ $totalNonVerified }}</p>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Non-Verified by Month</h5>
        @foreach($monthlyNonVerified as $month)
            <p class="mb-1">Non-Verified {{ $month['label'] }}: {{ $month['count'] }}</p>
        @endforeach
    </div>
</div>

<h2 class="h4 mb-2">Verified Users</h2>
<p class="text-muted mb-3">Showing {{ $users->count() }} most recent verified users</p>

<div class="table-responsive mb-5">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Created</th>
                <th>Verified On</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $user->email_verified_at->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">No verified users found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<h2 class="h4 mb-2">Unverified Users</h2>
<p class="text-muted mb-3">Showing {{ $unverifiedUsers->count() }} most recent unverified users</p>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($unverifiedUsers as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->created_at->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">No unverified users found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
