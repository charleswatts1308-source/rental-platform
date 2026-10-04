@extends('layouts.app')

@section('title', 'Cases')

@section('content')
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="mb-0">My Repair Cases</h1>
        <a href="{{ route('properties.create') }}" class="btn btn-outline-secondary">Register a property</a>
    </div>

    {{-- #65: the "you are verified" confirmation used to ride on a
         ?verified=1 query string, which the same-browser route silently
         dropped, so the commonest path through registration ended in
         silence. It is flashed now, like every other confirmation on
         this page, and read below. --}}
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
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
    @elseif($caseCount === 0)
        <div class="card mb-4">
            <div class="card-body">
                {{-- Explanation only, NO BUTTON. The property heading below
                     carries "Raise a case here", which names the property and
                     preselects it on the form. Two buttons offering the same
                     thing both appeared in this one state - a brand-new
                     tenant's first view - which is the worst place for it. --}}
                <h2 class="h5 card-title">You're ready to raise a repair case</h2>
                <p class="card-text">
                    Your property is on file. When something needs repairing, raise a case and we'll
                    send a formal notice to your landlord — then keep a dated record of what was sent
                    and what came back.
                </p>
            </div>
        </div>
    @endif

    {{-- Cases where the ball is with the tenant: these need a human decision,
         so they sit above the list rather than inside one property's group,
         where a tenant with two addresses could miss them. --}}
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

    {{-- Master-detail: property, then its cases. Newest property first,
         newest case first within each. The landlord control lives on the
         property header because the contact belongs to the property, not
         to any one case — correcting it is what rescues a stalled case. --}}
    @foreach($groups as $group)
        @php($property = $group['property'])
        <div class="card mb-4">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h6 mb-0">
                        {{ $property->address_line1 }}@if($property->address_line2), {{ $property->address_line2 }}@endif
                    </h2>
                    <span class="text-muted small">
                        {{ $property->city }} &middot; {{ $property->postcode }}
                        @if($property->currentLandlordContact)
                            &middot; landlord {{ $property->currentLandlordContact->name ?: $property->currentLandlordContact->email }}
                        @else
                            &middot; <span class="text-danger">no landlord contact</span>
                        @endif
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('cases.create', ['property' => $property->id]) }}" class="btn btn-sm btn-primary text-nowrap">Raise a case here</a>
                    <a href="{{ route('properties.contact.edit', $property) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Landlord</a>
                    <a href="{{ route('properties.edit', $property) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Property</a>
                </div>
            </div>

            @if($group['cases']->isEmpty())
                <div class="card-body">
                    <p class="text-muted small mb-0">No repair cases at this address yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Reference</th>
                                <th scope="col">Issue</th>
                                <th scope="col">Stage</th>
                                <th scope="col">Status</th>
                                <th scope="col">Opened</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group['cases'] as $case)
                                <tr>
                                    {{-- fs-6 lifts the reference off Bootstrap's default
                                         0.875em <code> size — it's the primary way into a
                                         case, so it shouldn't read as fine print. --}}
                                    <td><a href="{{ route('cases.show', $case->url_slug) }}"><code class="fs-6">{{ $case->url_slug }}</code></a></td>
                                    <td>{{ $case->category?->label ?? $case->category_key }}</td>
                                    <td>{{ $case->current_stage }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ str_replace('_', ' ', $case->status->value) }}</span>
                                    </td>
                                    <td>{{ $case->opened_at?->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    <p class="text-muted small mb-0">
        New here? <a href="{{ route('members.how-it-works') }}">How It Works</a> explains what happens
        after you send a notice, and when we chase your landlord for you.
    </p>

</div>
@endsection
