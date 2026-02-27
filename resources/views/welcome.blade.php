@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h1 class="mb-4">Welcome to Renters</h1>

<p>If you live in the UK and rent your home, this is for you.</p>

<p>You may have heard about the Renter Rights Act.</p>
<p>Its coming into force in 2 months time on May 1st.</p>
<p>It means you have much stronger rights of possession.</p>
<p>Your landlord can no longer evict you as they please.</p>
<hr>
<p>You can push back against shoddy conditions and rent increases.</p>
<p>To push back successfully you will need a systematic approach.</p>
<p>That is what I am offering here – a systematic process. </p>
<p>Pressing the landlord to comply with their legal obligations. </p>
<p>This will massively improve value-for-money on your rent.</p>
<p>Sign up is free - Sign up</p>
<hr>
<p>I'm starting with getting your repairs and improvements done.</p>
<p>All you need do now is sign up.</p>
<p>Then I'll ask for your agent and landlord details and repairsand improvements list. </p>
<p>The system will do the rest.

<p>Sign up is free - Sign up</p>
<hr>
<h3 class="mb-4">The Property Repair Process</h3>
<p>Using a legally correct letter/email protocol, we will engage with agent/landlord on your behalf to get repairs done.</p>
<p>Full corresponcence history will be maintained and availble for your inspection.</p>
<p>Landlords can no longer evade this responsibility by ending the agreement.</p>
<p>This will involve letter1, letter2, letter3, with a sensible interval betwen each.</p>
<p>Responses will range from immediate and helpful to "no reply"</p>
<p>In the event of "no reply" the landlord will be in breach of contract.</p>
<p>Letter4 will then advise that costs will be taken from the rent</p>
<hr>

<h1 class="mb-4">More details</h1>
<p>There are 4.6 million renter households in England</p>
<p>Your combined rents are £52 billion per year</p>
<p>This is the PRS sector - it's 3% of UK GDP </p>
<p>You're between Agiculture and Construction</p>
<p>It's big - that means big strength in numbers</p>
<p>As a joined up group you'd have powerful economic leverage.</p>
<p>This group has never existed before.</p>
<p>Join in helping create it.</p>
<p>Sign up</p>

<hr>
<h3 class="mb-4">Why now</h3>
<p>Crucially the Renters Rights Act is coming into force on May 1st this year.</p>
<p>This removes the landlord's right to end your tenancy without good cause - this "right" has been known as Section 21.</p>
<p>Section 21 became law 36 years ago - before the internet existed, so renters/tenants were powerless</p>
<p>Now its gone and you are free to enforce your new rights, with the power of the internet.</p>
<p>Your landlord cannot retaliate with eviction</p>
<p>Your landlord has to comply with your new rights</p>
<p>This is new territory</p>
<p>Sign up</p>
<hr>

<h3 class="mb-4">What to do</h3>
<p>You sign up and be counted</p>
<p>You provide details of your landlord and agent</p>
<p>You provide details of repairs needed in your property</p>


<div class="alert alert-success mb-4">
    <p class="lead mb-0"><strong>Join us - sign up and be counted</strong></p>
</div>

<div class="text-center mt-4">
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5">Register</a>
</div>
@endsection
