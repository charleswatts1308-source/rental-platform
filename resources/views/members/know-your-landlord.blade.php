@extends('layouts.app')

@section('title', 'Know Your Landlord')

@section('content')
<h1 class="mb-4">Know Your Landlord</h1>

<div class="alert alert-info mb-4">
    <h4 class="alert-heading">Understanding Your Landlord</h4>
    <p class="mb-0">Section 21 is abolished as of 1 May 2026. Section 21 gave landlords the power to always have the upper hand - they could remove you at any time. Few ever did, but it wasn't a risk anyone was willing to take. Now that's changed. You have a significant share of that power. What will you do with it?</p>
</div>

<div class="text-center mb-4">
    <button type="button" class="btn btn-secondary btn-lg">Join Us</button>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Why Know Your Landlord?</h2>

        <p>Under the Renters' Rights Act 2025, landlords have legal obligations regarding repairs, maintenance, and energy efficiency. Tenants can now raise concerns more freely and make enquiries without fear of retaliation.</p>

        <p>We offer a Landlord Contact Service that discovers what information is available in the public realm and helps establish communication between tenants and landlords.</p>

        {{-- <div class="mb-4">
            <button type="button" class="btn btn-outline-secondary">Yes Please</button>
        </div> --}}

        <p>Landlords routinely conduct checks on prospective tenants - bank statements, previous landlord references, and CCJ checks are standard practice. The principle of mutual transparency suggests that tenants should have access to similar information about their landlords.</p>

        {{-- <div class="mb-4">
            <button type="button" class="btn btn-outline-secondary">Yes Please</button>
        </div> --}}

        <p>Under the new rules, tenants can conduct their own research into landlord circumstances. The legislation also introduces a Landlord Register database to improve transparency across the sector.</p>

        <h3 class="h5 mt-4 mb-3">So Where Do You Start?</h3>

        <p>Start with your documentation - most tenants will have some form of paperwork, though accuracy may vary depending on the length of your tenancy.</p>

        <h4 class="h6 mt-3 mb-2">Follow the Documentation Trail</h4>
        <ul>
            <li><strong>Your tenancy agreement</strong> - Check whether the property was let in the name of an agent or landlord directly. The contract should ideally show the names of all parties involved.</li>
            <li><strong>Ask your agent</strong> - If you rent through an agent, you can request the landlord's name and address.</li>
            <li><strong>Land Registry</strong> - The registered owner of any property can be obtained from the Land Registry for a smallish fee.</li>
        </ul>

        <h4 class="h6 mt-3 mb-2">Follow the Money (Your Rent Money)</h4>
        <p>If documentation doesn't give you enough of a picture, you have no other option but to try tracing where your rent payments go through the banks. This is not easy and would need specialist lines of enquiry:</p>
        <ul>
            <li><strong>Bank statements</strong> - Check who receives your rent payments and research that name or company.</li>
            <li><strong>Companies House</strong> - If the landlord is a company, you can access their accounts, directors, and filing history for free.</li>
            <li><strong>Title register</strong> - Shows the registered owner and any charges (mortgages) on the property.</li>
        </ul>

        <p class="mt-3">Once you have this information, you can make informed decisions about your tenancy and communicate with your landlord from a position of knowledge.</p>
    </div>
</div>
@endsection
