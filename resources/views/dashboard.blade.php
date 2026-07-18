@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container py-4">

    <h1 class="mb-4">Your dashboard</h1>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ------------------------------------------------------------------
         Next action. The whole point of this page: a new tenant lands here
         straight after verifying and must be told what to do, in order.
         Property first, then case — the create-case form enforces that
         ordering anyway, so say it here rather than let them find out by
         hitting the block.
    ------------------------------------------------------------------- --}}
    @if($propertyCount === 0)
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 card-title">Start here</h2>
                <p class="card-text">
                    Before you can send a repair notice, we need to know which property you rent.
                    Register it once and every repair case you raise will be linked to it.
                </p>
                <a href="{{ route('properties.create') }}" class="btn btn-primary">Register your property</a>
            </div>
        </div>
    @elseif($cases->count() === 0)
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 card-title">You're ready to raise a repair case</h2>
                <p class="card-text">
                    Your property is on file. When something needs repairing, raise a case and we'll
                    send a formal notice to your landlord — then keep a dated record of what was sent
                    and what came back.
                </p>
                <a href="{{ route('cases.create') }}" class="btn btn-primary">Raise a repair case</a>
            </div>
        </div>
    @endif

    {{-- Cases where the ball is with the tenant: these need a human decision,
         so they sit above the general list. --}}
    @if($needsAttention->count() > 0)
        <div class="alert alert-warning">
            <h2 class="h6 mb-2">Needs your attention</h2>
            <ul class="mb-0">
                @foreach($needsAttention as $case)
                    <li>
                        <a href="{{ route('cases.show', $case->url_slug) }}">
                            {{ $case->category?->label ?? $case->category_key }}
                        </a>
                        — {{ $case->property->address_line1 }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($cases->count() > 0)
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Recent repair cases</h2>
            <a href="{{ route('cases.create') }}" class="btn btn-primary btn-sm">Raise a new case</a>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Property</th>
                        <th scope="col">Issue</th>
                        <th scope="col">Status</th>
                        <th scope="col">Opened</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cases->take(5) as $case)
                        <tr>
                            {{-- fs-6 lifts the reference off Bootstrap's default
                                 0.875em <code> size — it's the primary way into a
                                 case, so it shouldn't read as fine print. --}}
                            <td><a href="{{ route('cases.show', $case->url_slug) }}"><code class="fs-6">{{ $case->url_slug }}</code></a></td>
                            <td>
                                {{ $case->property->address_line1 }},
                                <span class="text-muted">{{ $case->property->postcode }}</span>
                            </td>
                            <td>{{ $case->category?->label ?? $case->category_key }}</td>
                            <td>
                                <span class="badge bg-info text-dark">{{ str_replace('_', ' ', $case->status->value) }}</span>
                            </td>
                            <td>{{ $case->opened_at?->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($cases->count() > 5)
            <p><a href="{{ route('cases.index') }}">See all {{ $cases->count() }} cases</a></p>
        @endif
    @endif

    <p class="text-muted small mb-0">
        New here? <a href="{{ route('members.how-it-works') }}">How It Works</a> explains what happens
        after you send a notice, and when we chase your landlord for you.
    </p>

</div>
@endsection
