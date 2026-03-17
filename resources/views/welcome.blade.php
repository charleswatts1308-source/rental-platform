@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h1 class="mb-4">Welcome to Renters</h1>

<p>If you live in the UK and rent your home<br>
   This is for you.<br>
<br>
The new Renters Rights Act is coming into force on May 1st - just {{ (int) \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::create(2026, 5, 1), false) }} days away.

<br>
This brings many changes but one powerful change stands out.<br>
<br>
You will be protected by law from unfair eviction.<br>
It frees you to safely push landlords for maintenance and repairs and a better deal.<br>
<br>
This is the biggest change for UK renters in 100 years.<br>
The removal of Section21 combined with the power of the internet means you can shake the UK rental market to its core. <br>
<br>
Think I’m exaggerating ?  <br>

4.7 million UK renters.<br>
One platform.<br>
Find out what happens when you all put your energy in the same place.<br>
<br>
Sign up is free - then you can see for yourself.<br>
<br>
Once registered - all will become clear.
<br>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-lg px-5 text-white" style="background-color: #047857;">Register</a>
</div>
@endsection


{{-- And to celebrate this huge change I bring you this platform to help you
pursue better conditions and better terms. --}}
