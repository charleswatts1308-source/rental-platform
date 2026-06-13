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
                    </p>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Stage</dt>
                        <dd class="col-7">{{ $case->current_stage }} of 4</dd>

                        <dt class="col-5">Severity</dt>
                        <dd class="col-7">{{ ucfirst($case->severity->value) }}</dd>

                        <dt class="col-5">Issue</dt>
                        <dd class="col-7">{{ $case->category?->label ?? $case->category_key }}</dd>

                        <dt class="col-5">Opened</dt>
                        <dd class="col-7">{{ $case->opened_at?->format('d M Y') }}</dd>

                        {{-- D15: an engaged-then-quiet held case shows the authorise
                             prompt instead of an auto-escalation date that will never
                             fire on its own. (Adjacent to snag #15; scoped, not a fix.) --}}
                        @if($case->silence_clock_started_at && $case->ball_with === 'landlord' && isset($case->silence_settings_snapshot['escalation.interval_days']) && !($authorisationPending ?? false))
                            <dt class="col-5">{{ ($case->landlord_engaged ?? false) ? 'Next notice (with your go-ahead)' : 'Next escalation' }}</dt>
                            <dd class="col-7">{{ $case->silence_clock_started_at->copy()->addDays((int) $case->silence_settings_snapshot['escalation.interval_days'])->format('d M Y') }}</dd>
                        @endif

                        @if($case->hold_until)
                            <dt class="col-5">Hold until</dt>
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
                    <p class="mb-1 fw-bold">{{ $case->landlordContact->name ?? $case->landlordContact->email }}</p>
                    <p class="mb-0 small text-muted">
                        {{ ucfirst($case->landlordContact->role->value) }}@if($case->landlordContact->organisation_name) — {{ $case->landlordContact->organisation_name }}@endif
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
