@extends('layouts.app')

@section('title', 'About Us')

@section('content')
<h1 class="mb-4">About renters.rent</h1>

<p class="mb-3">This site is here to help you make the most of your new rights.</p>
<p class="mb-3">It helps to put everything in writing.</p>


<hr class="my-4">

<p><a href="{{ route('members.how-it-works') }}">How It Works</a> sets out the process step
by step.</p>

@guest
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-2">Sign up</a>
@else
    <a href="{{ route('cases.create') }}" class="btn btn-primary btn-lg mt-2">Raise a repair case</a>
@endguest

@endsection
