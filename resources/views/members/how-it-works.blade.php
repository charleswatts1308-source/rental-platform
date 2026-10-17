@extends('layouts.app')

@section('title', 'How It Works')

@section('content')
<h1 class="mb-4">How It Works</h1>

{{-- <h3 class="mb-3">The Process</h3> --}}

    <p class="mb-3">Tell us what needs fixing, plus your landlord and agent contact details.</p>
    <p class="mb-3">We send a formal repair notice, properly worded, by email to your landlord.</p>
    <p class="mb-3">You can send photos and discuss and plan the work.</p>
    <p class="mb-3">Reminders are sent to your landlord after 14 days of not hearing back. </p>
    <p class="mb-3">You have a dated document trail.</p>

<br>
{{-- <h3 class="mb-3">The Stages</h3>

    <p class="mb-3">Stage 1 - Start the process and wait for a reply.</p>
    <p class="mb-3">Stage 2 - Landlord replies, engages, and the work gets done.</p>
    <p class="mb-3">Stage 3 - The repair remains unfixed.</p>
    <p class="mb-3">Stage 4 - Renter starts to take control of the situation.</p>
    <p class="mb-3">Stage 5 - Renter gets a result.</p> --}}


    {{-- <ol class="mb-4">
    <li class="mb-3">Tell us what needs fixing, plus your landlord and agent contact details.</li>
    <li class="mb-3">We send a formal repair notice, properly worded, by email to your landlord</li>
    <li class="mb-3">We set a clock running, and if no reply is received within 14 days we send another notice. </li>
    <li class="mb-3">If landlord responds, you can send photos and discuss and plan the repair process.</li>
    <li class="mb-3">If landlord stops responding and the clock reaches 14 days, we send another notice.</li>
    <li class="mb-3">The process moves forward through defined stages, each one dated.</li>
    <li class="mb-3">You keep the record. However it ends, you're left with a dated, documented trail of the whole exchange.</li>
</ol> --}}

    {{-- <li class="mb-3">Many renters are using AI to get the formal repair notice.</li>
    <li class="mb-3">The difference here is that a record of the corresponece is kept for you.</li>
    <li class="mb-3">You get your own private mailbox.</li>
    <li class="mb-3">You start a dialogue with your landlord which also starts a journey.</li>
    <li class="mb-3">They respond — or they don't. If they engage and fix it, that's logged. If they go quiet, the record moves forward through defined stages, each one dated.</li>
    <li class="mb-3">You keep the record. However it ends, you're left with a dated, documented trail of the whole exchange.</li> --}}


<br>
<a href="{{ route('cases.create') }}" class="btn btn-success btn-lg mb-4">Start a Repair Case</a>

<hr class="my-4">

<h3 class="mb-3">Whoever You Are - Whatever Property</h3>

<p> No matter who you are, whether your home is a house-share, a flat, a terrace, somewhere you've settled for years.</p>
<p> Its the same process - notify landlord and repeat until you get a result. </p>

<hr class="my-4">


<h3 class="mb-3">Where This Can Lead</h3>

<p>Most repairs get resolved once a landlord is properly informed.</p>
<p>Your landlord has a legal duty to keep the property in a "fit" state.</p>
<p>With correspondence on the record they can be held to account.</p>

<p>Where a repair isn't properly resolved, as you have a documented correspondence trail, you do have options.</p>
<p>Youcan pursue the legal route which can involve your local council's environmental health team,
    the Housing Ombudsman, or the First-tier Tribunal.
    Renters cannot advise on the legal route. But we do have an alternative route by which you can deal with a reluctant landlord.
    This is explained in our membership pages.
</p>

<br>
<a href="{{ route('cases.create') }}" class="btn btn-success btn-lg mb-4">Start a Repair Notice</a>

@endsection
