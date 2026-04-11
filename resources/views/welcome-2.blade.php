@extends('layouts.app')

@section('title', 'Home')

@section('content')
<p class="lead">Welcome to Renters <br>Kickstart your rented home's repairs and maintenance. </p>

<p class="lead">The UK's private rented sector, the PRS,  is one of the largest industries in the country.</p>
<p>Bigger by revenue than defence. Bigger than tourism. A colossal transfer of money, every month, from tenants to landlords.</p>
<p>4.7 million households. Every age, income, and background. The money flowing in is enormous.
    What flows back &mdash; in terms of maintained, safe, fit-for-purpose homes &mdash; is far less impressive.</p>

<p>14% of rentals are flat conversions, 34% are terraced houses, all typically older Victorian or Edwardian stock. 48% of the entire sector lives in one of these two property types.</p>

<p>Many of these properties have ageing heating systems, poor insulation,
    and structural characteristics that make damp and cold a persistent problem rather than an occasional one.</p>

<p>A significant portion of PRS housing entered the rental market because owners moved on, inherited property, or couldn't sell.
    The tenant inherits whatever condition the property was in.</p>

<hr>

<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">Renters Rights Act - new rules</h5>
<p>Section 21 is gone. For the last 38 years a landlord could respond to any complaint with a no-fault eviction notice.
    That tool is gone. You can now raise concerns about your home without fear of losing it.
    You are now protected by new laws contained in the Renters Rights Act 2026.
</p>

<hr>

<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">You still have to ask</h5>
<p>Knowing you can now ask is a great thing. But getting a helpful response will still take much time and effort.
    Today's landlords have become very used to doing the bare minimum, and will not be keen to change.
    Many single property landlords are likely to be frightened by the cost implications.</p>
<p> What do you do if your landlord or agent does not enter into a useful dialogue?
    Is your landlord is actually unwilling or financially unable to get the work done? What to do then?</p>
    You're paying big rent, you deserve to be a valued customer.<br>
    We'll get you there with our Landlord Contact Service. New tools for a new era.
<hr>

<h5 class="mb-1" style="border-left: 6px solid #047857; padding-left: 0.5rem;">Our Landlord Contact Service</h5>
<p> Our service runs a series of repeated formal letters requesting that the landlord enters into a meaningful dialogue about repairs and maintenance.
    At the same time we build a profile of your landlord, similar to the vetting they apply to you.
    Then you can see for yourself where you stand.
    This is new territory, combining the internet with a generation of unhappy renters. Much can happen.</p>

    You provide the property details, repairs list, agent and landlord details. We do the rest.</p>
<p>We start with an initial letter requesting confirmation that your landlord is contactable, identifiable,
    and has capacity to fulfil their responsibilities.
    Your landlord may be good and responsive or simply not contactable,
    or anywhere in between. You should find out where you stand.</p>
<p>We offer a professional service with documented records that will aid you in pursuit of your goal,
    whether that be getting repairs done, getting the rent reduced, even buying your home.</p>

    <p>Our members pages describe everything in fine detail.</p>
    <p>Sign up is free. Your first letter is free.</p>

<hr>

<p class="lead">Let's get the UK's rented homes fit for the 21st century.</p>
<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-lg px-5 text-white" style="background-color: #047857;">Create Your Account &mdash; Free</a>
</div>
@endsection
