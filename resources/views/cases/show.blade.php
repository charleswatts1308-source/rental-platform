@extends('layouts.app')

@section('title', 'Case ' . $case->url_slug)

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="mb-1">Case <code>{{ $case->url_slug }}</code></h1>
            <p class="text-muted mb-0">
                {{ $case->property->address_line1 }},
                @if($case->property->address_line2){{ $case->property->address_line2 }}, @endif
                {{ $case->property->city }}, {{ $case->property->postcode }}
            </p>
        </div>
        <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary">Back to all cases</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-muted text-uppercase">Status</h2>
                    <p class="h4 mb-2">
                        <span class="badge bg-info text-dark">{{ str_replace('_', ' ', $case->status->value) }}</span>
                        {{-- D14 — tenant's cosmetic framing of an exhausted case. --}}
                        @if($case->exhausted_stance)
                            <span class="badge bg-secondary">{{ $case->exhausted_stance->label() }}</span>
                        @endif
                    </p>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Stage</dt>
                        {{-- #16 — denominator reads the live ladder length, not a literal 4. --}}
                        <dd class="col-7">{{ $case->current_stage }} of {{ \App\Models\Setting::get('escalation.max_notices', 4) }}</dd>

                        <dt class="col-5">Severity</dt>
                        <dd class="col-7">{{ ucfirst($case->severity->value) }}</dd>

                        <dt class="col-5">Issue</dt>
                        <dd class="col-7">{{ $case->category?->label ?? $case->category_key }}</dd>

                        <dt class="col-5">Opened</dt>
                        <dd class="col-7">{{ $case->opened_at?->format('d M Y') }}</dd>

                        {{-- #14 / #15 / #21-tail — shared state-aware-display predicate
                             on the model. showsNextEscalation() is true only while the
                             landlord clock is actively counting down (suppressed on
                             on_hold, dormant, closed, and exhausted). The D15
                             authorisation-pending case shows the authorise prompt
                             instead of a date that will not fire on its own. --}}
                        @if($case->showsNextEscalation() && !($authorisationPending ?? false))
                            <dt class="col-5">{{ ($case->landlord_engaged ?? false) ? 'Next notice (with your go-ahead)' : 'Next escalation' }}</dt>
                            <dd class="col-7">{{ $case->nextEscalationDate()->format('d M Y') }}</dd>
                        @endif

                        {{-- #15 — hold_until shows only while actually on hold; after
                             the sweep releases it the column persists as history but
                             must not read as an active pause. --}}
                        @if($case->showsHoldUntil())
                            <dt class="col-5">Paused until</dt>
                            <dd class="col-7">{{ $case->hold_until->format('d M Y') }}</dd>
                        @endif

                        @if($case->closed_at)
                            <dt class="col-5">Closed</dt>
                            <dd class="col-7">{{ $case->closed_at->format('d M Y') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-muted text-uppercase">Recipient</h2>
                    @php($recipient = $case->landlordRecipient())
                    <p class="mb-1 fw-bold">{{ $recipient?->name ?? $recipient?->email }}</p>
                    <p class="mb-0 small text-muted">
                        {{ ucfirst($recipient?->role->value) }}@if($recipient?->organisation_name) — {{ $recipient->organisation_name }}@endif
                    </p>
                </div>
            </div>

            @include('cases._action_panel')
        </div>

        <div class="col-lg-8">
            <h2 class="h5 mb-3">Correspondence</h2>

            @if($messages->isEmpty())
                <div class="alert alert-secondary">No messages yet.</div>
            @else
                @foreach($messages as $message)
                    @include('cases._message_card', ['message' => $message])
                @endforeach
            @endif

            @if($quarantined->isNotEmpty())
                <div class="alert alert-warning mt-4">
                    <h3 class="h6 mb-2">Unverified messages</h3>
                    <p class="mb-2 small">
                        We received {{ $quarantined->count() }} message{{ $quarantined->count() === 1 ? '' : 's' }}
                        that didn't come from your landlord's expected email address. Review carefully — they may be
                        legitimate (e.g. a different agent at the same firm) or unrelated.
                    </p>
                    @foreach($quarantined as $message)
                        @include('cases._message_card', ['message' => $message])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
