@extends('layouts.app')

@section('title', 'Repair Notices')

@section('content')
<h1 class="mb-4">Repair Notices</h1>

<p>Something needs fixing?. <br>
    Here is how to use the platform to deal with it.</p>

<hr>

<h3 class="mb-3">How It Works</h3>

<p>You tell us what needs fixing.
    We send a formal notice to your landlord and agent on your behalf.
    The notice references your rights under the Renters Rights Act and sets a reasonable deadline for response,
    You approve every letter before it's sent.</p>
<hr>

<h3 class="mb-3">Why This Works</h3>

<p>A formal notice from an organised platform, arriving once, then repeated at escalating levels,
    is a different thing from a tenant asking informally.
    It is obvious that a system is keeping records on your behalf.</p>

<hr>

<h3 class="mb-3">The Escalation Path</h3>

<p>Depending on the response, the platform will progress through a defined sequence: initial notice,
    reminder, formal demand, and where necessary, referral to the relevant council or ombudsman.</p>

<p>If a landlord repeatedly ignores their obligations, the documented record of failed notices
    will, at some point, empower the tenant to arrange the repairs and deduct the cost from rent.
    Then its the landlord's choice accept it, or to go to court
    — facing a tenant with a paper trail they cannot dispute.
    This specific position has yet to be tested in the courts under the new legislation,
    but given the shift in the legal landscape, it is only a matter of time.</p>

<p>Patient. Persistent. Documented.</p>

<hr>


<a href="{{ route('rentals.create') }}" class="btn btn-success btn-lg mb-4">Start a Repair Notice</a>

@endsection
