@extends('layouts.app')
@section('title', 'Admin - Case ' . $case->url_slug)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Case <code>{{ $case->url_slug }}</code></h1>
    <a href="{{ route('admin.cases.index') }}" class="btn btn-sm btn-outline-secondary">Back to oversight</a>
</div>

<div class="alert alert-secondary">
    Read-only. This view never changes a case — adjustments to a stuck case are
    made manually (break-glass) so every state change keeps a recorded cause.
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">State</h2>
                <dl class="row mb-0 small">
                    <dt class="col-5">Status</dt>
                    <dd class="col-7"><span class="badge bg-info text-dark">{{ str_replace('_', ' ', $case->status->value) }}</span></dd>

                    <dt class="col-5">Ball with</dt>
                    <dd class="col-7">{{ $case->ball_with ?? '—' }}</dd>

                    <dt class="col-5">Stage</dt>
                    <dd class="col-7">{{ $case->current_stage }} of {{ \App\Models\Setting::get('escalation.max_notices', 4) }}</dd>

                    <dt class="col-5">Landlord engaged</dt>
                    <dd class="col-7">{{ $case->landlord_engaged ? 'Yes' : 'No' }}</dd>

                    <dt class="col-5">Opened</dt>
                    <dd class="col-7">{{ $case->opened_at?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5">Clock started</dt>
                    <dd class="col-7">{{ $case->silence_clock_started_at?->format('d M Y') ?? '—' }}</dd>

                    {{-- Same state-aware predicate as the tenant view (#14/#15/#21-tail). --}}
                    @if($case->showsNextEscalation())
                        <dt class="col-5">Next escalation</dt>
                        <dd class="col-7">{{ $case->nextEscalationDate()->format('d M Y') }}</dd>
                    @endif

                    @if($case->showsHoldUntil())
                        <dt class="col-5">Hold until</dt>
                        <dd class="col-7">{{ $case->hold_until->format('d M Y') }}</dd>
                    @endif

                    @if($case->closed_at)
                        <dt class="col-5">Closed</dt>
                        <dd class="col-7">{{ $case->closed_at->format('d M Y') }}</dd>
                    @endif

                    @if($case->exhausted_stance)
                        <dt class="col-5">Stance</dt>
                        <dd class="col-7">{{ $case->exhausted_stance->label() }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">Parties</h2>
                <p class="mb-1 small"><strong>Tenant:</strong> {{ $case->tenant?->name }} ({{ $case->tenant?->email }})</p>
                <p class="mb-1 small"><strong>Property:</strong> {{ $case->property?->address_line1 }}, {{ $case->property?->postcode }}</p>
                <p class="mb-0 small"><strong>Recipient:</strong> {{ $case->landlordContact?->name ?? $case->landlordContact?->email }}</p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <h2 class="h5 mb-3">Event trail</h2>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Event</th>
                        <th scope="col">Actor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td class="text-nowrap">{{ $event->occurred_at?->format('d M Y H:i') }}</td>
                            <td><code>{{ $event->event_type }}</code></td>
                            <td>{{ $event->actor_label }}@if($event->actor) ({{ $event->actor->name }})@endif</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">No events recorded</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
