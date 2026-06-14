@extends('layouts.app')

@section('title', 'If your landlord will not engage')

@section('content')
{{--
    D14 — signposting page for an escalation_exhausted case. STUB ONLY:
    shape, not content. No named bodies, thresholds, or deadlines — the
    real wording is deferred to the solicitor pass (design doc D5: "content
    deferred permanently — edit rows, not code"). Members-wall (auth), not
    in public nav. Linked from the exhausted case page + the exhaustion
    notice email.
--}}
<h1 class="mb-4">If your landlord will not engage</h1>

<p class="lead">The automated escalation route has run its course. Your
    landlord did not respond to the full sequence of formal notices.</p>

<p>That silence is not the end of the road — and it is now well documented.
    The complete correspondence record on your case is ready to be shared as
    evidence with any of the routes below. renters.rent does not act on your
    behalf with these bodies; the decision and the next step are yours.</p>

<hr>

<h3 class="mb-3">Your documented trail supports these routes</h3>

{{-- Placeholder sections only — solicitor-pass wording to follow. --}}
<h5>Your local council</h5>
<p class="text-muted">Guidance to follow.</p>

<h5>The relevant ombudsman</h5>
<p class="text-muted">Guidance to follow.</p>

<h5>The courts</h5>
<p class="text-muted">Guidance to follow.</p>

<h5>Your evidence bundle</h5>
<p class="text-muted">Guidance to follow.</p>

<hr>

<a href="{{ route('cases.index') }}" class="btn btn-outline-primary mb-4">Back to my cases</a>

@endsection
