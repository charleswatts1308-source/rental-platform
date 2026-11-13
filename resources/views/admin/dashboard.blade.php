@extends('layouts.app')
@section('title', 'Admin - Dashboard')
@section('content')
<h1 class="mb-1">Dashboard</h1>
<p class="text-muted mb-4">
    Read-only. Environment: <strong>{{ $environment }}</strong> ·
    as at {{ now()->format('d M Y H:i') }}
</p>

{{-- ---- Headline counts ------------------------------------------------ --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Registrations</div>
            <div class="fs-3">{{ $users['total'] }}</div>
            <div class="small">{{ $users['this_week'] }} this week · {{ $users['unverified'] }} unverified</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Properties</div>
            <div class="fs-3">{{ $users['properties'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Cases</div>
            <div class="fs-3">{{ $cases['total'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Letters to landlords</div>
            <div class="fs-3">{{ $letters['sent'] }}</div>
            <div class="small">{{ $letters['sent_this_week'] }} this week · {{ $letters['replies'] }} landlord replies ({{ $letters['replies_this_week'] }} this week)</div>
        </div>
    </div>
</div>

<h2 class="h5">Cases by status</h2>
<div class="table-responsive mb-4">
    <table class="table table-sm w-auto">
        <tbody>
            @foreach($cases['by_status'] as $status => $n)
                <tr>
                    <td>{{ str_replace('_', ' ', $status) }}</td>
                    <td class="text-end">{{ $n }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- ---- Health ---------------------------------------------------------- --}}
<h2 class="h5">Scheduler — daily sweep runs, last 7 days</h2>
<p class="mb-2">
    @if($sweeps['today_ok'])
        <span class="text-success">Today: 1 run.</span>
    @else
        <span class="text-danger"><strong>Today: {{ $sweeps['today'] }} run(s).</strong></span>
        The sweep runs at 06:15; before then today shows 0.
    @endif
    Last real run: {{ $sweeps['last'] ? \Illuminate\Support\Carbon::parse($sweeps['last'])->format('d M Y H:i') : 'never' }}.
</p>
<div class="table-responsive mb-1">
    <table class="table table-sm w-auto">
        <tbody>
            @foreach($sweeps['days'] as $day => $runs)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($day)->format('D d M') }}</td>
                    <td class="text-end {{ $runs === 1 ? '' : 'text-danger fw-semibold' }}">{{ $runs }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="small text-muted mb-4">
    Expect 1 a day. 0 means no run, 2 means two schedulers. A run is only
    recorded when there is at least one active case, so with no active cases
    every day shows 0.
</p>

{{-- ---- Needs attention ---------------------------------------------- --}}
<h2 class="h5">Needs attention</h2>
@if($attention['unanswered']->isEmpty() && $attention['contact_failed']->isEmpty())
    <p class="text-muted mb-4">Nothing waiting.</p>
@else
    <ul class="mb-4">
        @foreach($attention['unanswered'] as $msg)
            <li>
                Contact message from {{ $msg->user?->email ?? 'deleted user' }},
                {{ $msg->created_at?->format('d M Y') }}:
                <a href="{{ route('admin.contact-messages.show', $msg) }}">{{ $msg->subject }}</a> — unanswered
            </li>
        @endforeach
        @foreach($attention['contact_failed'] as $case)
            <li>
                Letter could not be delivered:
                <a href="{{ route('admin.cases.show', $case) }}"><code>{{ $case->url_slug }}</code></a>,
                {{ $case->property?->address_line1 }}, {{ $case->property?->postcode }}
            </li>
        @endforeach
    </ul>
@endif

{{-- ---- Recent registrations ---------------------------------------- --}}
<h2 class="h5">Newest registrations</h2>
<div class="table-responsive mb-4">
    <table class="table table-striped table-sm align-middle">
        <thead>
            <tr>
                <th scope="col">Joined</th>
                <th scope="col">Email</th>
                <th scope="col">Name</th>
                <th scope="col">Verified</th>
                <th scope="col" class="text-end">Properties</th>
                <th scope="col" class="text-end">Cases</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users['recent'] as $user)
                <tr>
                    <td class="text-nowrap">{{ $user->created_at?->format('d M Y H:i') }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email_verified_at ? 'Yes' : 'No' }}</td>
                    <td class="text-end">{{ $user->properties_count }}</td>
                    <td class="text-end">{{ $user->cases_count }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">No registrations</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ---- Recent cases ---------------------------------------------------- --}}
<h2 class="h5">Newest cases</h2>
<div class="table-responsive mb-4">
    <table class="table table-striped table-sm align-middle">
        <thead>
            <tr>
                <th scope="col">Opened</th>
                <th scope="col">Reference</th>
                <th scope="col">Tenant</th>
                <th scope="col">Property</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end">Letters</th>
                <th scope="col">Landlord replied</th>
                <th scope="col">Last activity</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cases['recent'] as $case)
                <tr>
                    <td class="text-nowrap">{{ $case->opened_at?->format('d M Y') ?? '—' }}</td>
                    <td><a href="{{ route('admin.cases.show', $case) }}"><code>{{ $case->url_slug }}</code></a></td>
                    <td>{{ $case->tenant?->email }}</td>
                    <td>{{ $case->property?->city }}, {{ $case->property?->postcode }}</td>
                    <td><span class="badge bg-secondary">{{ str_replace('_', ' ', $case->status->value) }}</span></td>
                    <td class="text-end">{{ $case->letters_count }}</td>
                    <td>{{ $case->landlord_replies_count > 0 ? 'Yes (' . $case->landlord_replies_count . ')' : 'No' }}</td>
                    <td class="text-nowrap">{{ $case->last_activity_at?->format('d M Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted">No cases</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ---- Visitors -------------------------------------------------------- --}}
<h2 class="h5">Visitors — last 14 days, crawlers excluded</h2>
@if(! $visitors['enabled'] && ! $visitors['any'])
    <p class="text-muted mb-4">
        Page tracking is switched off on this site (TRACK_PAGE_VIEWS), so there
        are no visitor figures.
    </p>
@else
    @unless($visitors['enabled'])
        <p class="small text-muted">Page tracking is switched off now; figures below are from when it was on.</p>
    @endunless
    <div class="row">
        <div class="col-md-5">
            <div class="table-responsive mb-4">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col" class="text-end">Visitors</th>
                            <th scope="col" class="text-end">Page views</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($visitors['days'] as $day => $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($day)->format('D d M') }}</td>
                                <td class="text-end">{{ $row['visitors'] }}</td>
                                <td class="text-end">{{ $row['views'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-7">
            <h3 class="h6">Top pages, last 30 days</h3>
            <div class="table-responsive mb-4">
                <table class="table table-sm">
                    <tbody>
                        @forelse($visitors['top_pages'] as $page)
                            <tr>
                                <td class="text-break">{{ parse_url($page->page_name, PHP_URL_PATH) ?: '/' }}</td>
                                <td class="text-end">{{ $page->views }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No page views yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <p class="small text-muted">Visitors = distinct IP addresses that day.</p>
@endif
@endsection
