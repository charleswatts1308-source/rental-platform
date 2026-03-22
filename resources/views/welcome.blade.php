@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h1 class="mb-4">Welcome to Renters</h1>

<p>If you live in the UK and rent your home<br>
   This is for you.<br>

<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">New Rules for Private Renters</h5>
The new Renters Rights Act is coming into force on May 1st -
just {{ (int) \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::create(2026, 5, 1), false) }} days away.
<br>
This brings many beneficial changes but one powerful change stands out.<br>
<br>
Section 21 of the Housing Act 1988 in England and Wales will be abolished.<br>
It’s the end of “no-fault evictions”<br>
After May 1st you will be protected by law from unfair eviction.<br>
<br>
This is the biggest change for UK renters in 100 years.<br>
<br>
<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">What it means for you</h5>
You are free to safely push landlords for maintenance and repairs and a better deal.<br>
<br>
But that does not mean that landlords and agents will be any better at responding.<br>
Probably worse as they come under increasing pressure from renters’ requests. <br>
And you probably know how much energy and persistence it can take just to get a response<br>
<br>
But now you can ask without fear of retaliation, and you have the power of the internet.<br>
Which is why we are here.<br>
<br>
<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">Our New Repairs Notice</h5>
We offer a new service that will do the heavy-lifting and enter into formally-written email
correspondence with your landlord / agent.
We will start with presenting your repairs list and asking for a timescale to meet their obligations. <br>
<br>
You have been handed a really immense amount of power - make sure you use it to the max.<br>
<br>
Our members pages explain it all in detail. <br>
<br>
Sign up is free - then you can see for yourself.<br>
<br>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-lg px-5 text-white" style="background-color: #047857;">Register</a>
</div>
@endsection


{{-- And to celebrate this huge change I bring you this platform to help you
pursue better conditions and better terms. --}}
