@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h3 class="mb-4">Welcome to Renters</h3>

<p>If you live in the UK and rent your home<br>
   This is for you.<br>
<br>

A powerful change in our UK rental Law is coming on May 1st.<br>
<br>
The change brings protection from unfair eviction<br>
It frees you to safely push landlords for maintenance and repairs and a better deal.<br>
<br>

Welcome to our Renters letter writing service
Think letter 1, etc until they give in or give up
<br><br>
Sign up to our Landlord Contact Service<br>
and improve the condition of the UK PRS
<br>



<div class="alert alert-success mb-4">
    <p class="lead mb-0"><strong>Join us - sign up and be counted</strong></p>
</div>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Register</a>
</div>
@endsection
