@extends('layouts.app')

@section('title', 'How It Works')

@section('content')
<h1 class="mb-4">How It Works</h1>

{{-- <h3 class="mb-3">The Process</h3> --}}

    <p class="mb-3">Tell us what needs fixing, plus your landlord and agent contact details.</p>
    <p class="mb-3">We compose a formal repair notice, properly worded, and ask you to review and confirm.</p>
    <p class="mb-3">We send the repair notice by email to your landlord.</p>
    <p class="mb-3">We expect your landlord to reply, acknowledging receipt.</p>
    <p class="mb-3">Then you can send photos and discuss and plan the work.</p>
    <p class="mb-3">Reminders are sent to your landlord after 14 days of not hearing back. </p>
    <p class="mb-3">Your landlord has new obligations, can’t evict in response, and legally must act.</p>
    <p class="mb-3">The absence of a reply means you have next steps.</p>
    <p class="mb-3">And you have a dated document trail.</p>

<br>

<br>
<a href="{{ route('cases.create') }}" class="btn btn-primary btn-lg mb-4">Start a Repair Case</a>

<hr class="my-4">

<h3 class="mb-3">Whoever You Are - Whatever Property</h3>

<p> No matter who you are, and whether your home is a house-share, a flat, a terrace or somewhere you've settled for years:</p>
<p> It's the same process - notify landlord and repeat until you get a result. </p>

<hr class="my-4">


<h3 class="mb-3">Where This Can Lead</h3>

<p>Most repairs get resolved once a landlord is properly informed.</p>
<p>Your landlord has a legal duty to keep the property in a "fit" state.</p>
<p>With correspondence on the record they can be held to account.</p>

<p>Where a repair isn't properly resolved, as you have a documented correspondence trail, you do have options.</p>
<p>Your local council's environmental health team can already step in where a landlord fails to carry out repairs.</p>
<p>In England, the Renters' Rights Act is setting up new bodies for private renting:</p>
<p>A Landlord Ombudsman, which every private landlord will have to join and which will handle tenants' complaints.</p>
<p>A national database of private landlords.</p>
<p>As these come into operation, a documented correspondence trail is exactly the evidence they will look for.</p>

<br>
<a href="{{ route('cases.create') }}" class="btn btn-primary btn-lg mb-4">Start a Repair Case</a>

@endsection
