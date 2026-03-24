@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">Does your landlord take repairs seriously?</h5>
<p>Most don't. Now you can do something about it.</p>

<hr>

<p>From 1 May 2026, "no-fault" evictions are abolished in England &mdash;
just {{ (int) \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::create(2026, 5, 1), false) }} days away.<br>
For the first time in decades, you can push back without fear of losing your home.<br>
Renters gives you the tools to do exactly that &mdash; from day one.</p>

<hr>

<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">What we do</h5>
<p>Register your rental and we'll handle the hard part.</p>
<p>We write formal repair notices on your behalf &mdash; professionally worded correspondence that sets out your landlord's legal obligations and asks for a clear timescale to meet them.</p>
<p>No more chasing. No more being fobbed off. A written record your landlord has to take seriously.</p>

<hr>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-lg px-5 text-white" style="background-color: #047857;">Register free</a>
    <p class="mt-2">Free to join. Your landlord won't know you're here.</p>
</div>
@endsection
