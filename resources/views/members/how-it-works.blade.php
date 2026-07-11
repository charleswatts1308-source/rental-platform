@extends('layouts.app')

@section('title', 'How It Works')

@section('content')
<h1 class="mb-4">How It Works</h1>

<h3 class="mb-3">The Process</h3>

<ol class="mb-4">
    <li class="mb-3">Tell us what needs fixing. The repair, and your landlord's or agent's contact details.</li>
    <li class="mb-3">We send a formal notice. A properly worded repair notice goes to your landlord, and the clock starts.</li>
    <li class="mb-3">They respond — or they don't. If they engage and fix it, that's logged. If they go quiet, the record moves forward through defined stages, each one dated.</li>
    <li class="mb-3">You keep the record. However it ends, you're left with a dated, documented trail of the whole exchange.</li>
</ol>

<a href="{{ route('cases.create') }}" class="btn btn-success btn-lg mb-4">Start a Repair Notice</a>

<hr class="my-4">

<h3 class="mb-3">Whoever You Are</h3>

<p>Renting looks different at every stage — a student houseshare, a first flat, a family
home, somewhere you've settled for years. The repair process doesn't change. If something
needs fixing and your landlord isn't acting, the notice is the same one, whoever you are.</p>

<p class="text-muted">Student · Young professional · Family · Long-term renter</p>

<hr class="my-4">

<h3 class="mb-3">Whatever Property</h3>

<p>Damp in a period terrace, a defect in a new build, a draught nobody will trace, a boiler
that fails every winter. Old or new, flat or house, disrepair is disrepair — and the duty to
put it right doesn't depend on the age of the building.</p>

<p class="text-muted">Period terrace · Ex-council · New build · Purpose-built flat · HMO room</p>

<hr class="my-4">

<h3 class="mb-3">Where This Can Lead</h3>

<p>Most repairs get resolved once a landlord sees a matter is being handled properly and on
the record. But not all, and it's worth knowing where the process can go.</p>

<h5 class="mb-2 mt-4">The landlord acts</h5>
<p>The repair is done and the exchange is logged. For most cases, this is the end of it.</p>

<h5 class="mb-2 mt-4">The landlord engages but stalls</h5>
<p>Some movement, no resolution. The record captures the delay — dates, what was promised,
what wasn't done.</p>

<h5 class="mb-2 mt-4">The landlord stays silent</h5>
<p>The notice period passes with no response. The record shows a clear, dated account of
proper notice given and ignored.</p>

<p class="mt-4">Where a matter isn't resolved, a documented correspondence trail is the thing
outside bodies ask to see — your local council's environmental health team, the Housing
Ombudsman, or the First-tier Tribunal, depending on the issue. renters.rent doesn't take a
case to those bodies or advise which applies. It gives you the record they'd want, should
you choose to go further.</p>
@endsection
