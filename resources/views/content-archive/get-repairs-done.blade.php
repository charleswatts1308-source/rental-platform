@extends('layouts.app')

@section('title', 'Get Repairs Done')

@section('content')
<h1 class="mb-4">Get Repairs Done</h1>

<p>Under the new Renters Rights Act, your rights to repairs are greatly strengthened. But you still have to make your repair requests effectively.</p>

<hr>

<p>You may be one of the lucky ones who can get things done with a simple phone call.</p>

<p>But if not then you'll know it's a struggle - and here we can help.</p>

<hr>

<p>The service we offer will do all the hard work, preparing emails in a correct formal manner noting everything necessary and expected timescales for completion of repair.</p>

<p>All emails will be sent by you, not us.</p>

<p>We will run a follow-up process and offer you next-step emails as required.</p>

<hr>

<p>There is actually a well established protocol by which an Agent or Landlord can be held to account for non-compliance of repair obligations. It's just a tedious repetitive process, which is what computers are good at.</p>

<p>There is also a new Private Rented Sector Ombudsman to handle disputes between landlords and tenants should it come to that.</p>

<hr>

<p>Remember, your landlord can no longer threaten you with retaliatory eviction - that is now a thing of the past.</p>

<hr>

<div class="alert alert-success mb-4">
    <p class="lead mb-0"><strong>If this service is of interest to you</strong></p>
</div>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Sign Up Here</a>
</div>
@endsection
