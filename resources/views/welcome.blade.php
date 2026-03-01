@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h1 class="mb-4">Welcome to Renters</h1>


<p>Welcome to Renters, I hope you enjoy my website. </p>
<p>If you live in the UK and rent your home, this is for you. </p>

<p>You may have heard about the Renter Rights Act.<br>
Its coming into force in 2 months time on May 1st. </p>
<ul>
<li>It means you have much stronger rights of possession. </li>
<li>Your landlord can no longer evict you as they please. </li>
<li>You can push to improve conditions and oppose rent increases. </li>
<li>Ask to renegotiate the Agreement and more. </li>
</ul>
<p>You, the Renter is greatly more empowered. <br>
And that power is magnified when working at scale. </p>
There are 4.5 million renter households in England <br>
Your combined rents are £52 billion per year <br>
It's 3% of UK GDP, between Agiculture and Construction </p>
<p>That's a lot of economic muscle. <br>
And that is the point of this website <br>
To explore your new powers at scale. </p>
<p>If all 4.5 million of you join, well ... the sky's the limit</p>
<p>But let's start with a Repairs & Improvements Service... </p>
<p>Our members area has content on all this and more. </p>
<p>I invite you to sign up and be counted as a member of Renters </p>

<div class="alert alert-success mb-4">
    <p class="lead mb-0"><strong>Join us - sign up and be counted</strong></p>
</div>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Register</a>
</div>
@endsection
