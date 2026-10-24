@extends('layouts.app')

@section('title', 'About Us')

@section('content')
<h1 class="mb-4">About Us</h1>

<p class="mb-3">It’s me and the software that makes up the platform. That's us.</p>
<p class="mb-3">The reason? I feel bad about the unfairness of multiple home ownership.</p>
<p class="mb-3">And this point in time, just after the Renters' Rights Act, is actually quite momentous.</p>
<p class="mb-3">Renters have new powerful rights of possession.</p>
<p class="mb-3">For the first time in 40 years renters can dare to make requests of their landlord.</p>
<p class="mb-3">Your landlord has new obligations, can’t evict in response, and must act.</p>
<p class="mb-3">But to do it well you need the functions of a small office.</p>
<p class="mb-3">Letters, record keeping, reminders, the new rule book.</p>
<p class="mb-3">Most of us find this difficult at a personal level.</p>
<p class="mb-3">And landlords will not offer; you do have to ask.</p>
<p class="mb-3">So, I offer this simple platform to undertake your correspondence.</p>


<hr class="my-4">

<p><a href="{{ route('members.how-it-works') }}">How It Works</a> sets out the process step
by step.</p>

@guest
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-2">Sign up</a>
@else
    <a href="{{ route('cases.create') }}" class="btn btn-primary btn-lg mt-2">Raise a repair case</a>
@endguest

@endsection
