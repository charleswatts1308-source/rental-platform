@extends('layouts.app')

@section('title', 'Home')

@section('content')

<div class="text-center py-4">
    <h1 class="fw-bold">GET REPAIRS SORTED. FAST</h1>
    <p class="lead">Submit and track maintenance requests with ease.</p>
    <div class="mt-3">
        <a href="{{ route('register') }}" class="btn btn-lg text-white me-2" style="background-color: #047857;">Submit a Request</a>
        <a href="{{ route('register') }}" class="btn btn-lg" style="color: #047857; border-color: #047857;">Track a Repair</a>
    </div>
</div>

<hr>

<div class="text-center py-4">
    <div class="row">
        <div class="col-md-4 mb-3">
            <i class="bi bi-clipboard-check" style="font-size: 2rem; color: #047857;"></i>
            <p class="fw-bold mt-2 mb-0">EASY SUBMISSION</p>
        </div>
        <div class="col-md-4 mb-3">
            <i class="bi bi-lightning-charge" style="font-size: 2rem; color: #047857;"></i>
            <p class="fw-bold mt-2 mb-0">FAST RESPONSE</p>
        </div>
        <div class="col-md-4 mb-3">
            <i class="bi bi-eye" style="font-size: 2rem; color: #047857;"></i>
            <p class="fw-bold mt-2 mb-0">TRANSPARENT UPDATES</p>
        </div>
    </div>
</div>

<div class="text-center py-4">
    <div class="d-flex align-items-center mb-4">
        <hr class="flex-grow-1" style="border-color: #047857;">
        <h1 class="fw-bold mx-3 mb-0">HOW IT WORKS</h1>
        <hr class="flex-grow-1" style="border-color: #047857;">
    </div>
    <div class="row">
        <div class="col mb-3">
            <div class="border rounded p-4 pt-3 position-relative" style="margin-top: 1.2rem; cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseSubmit">
                <span class="position-absolute start-50 translate-middle px-3 fw-bold fs-3" style="top: 0; background: var(--bs-body-bg); color: #047857;">1</span>
                <p class="fw-bold mb-0 mt-2">SUBMIT</p>
                <span class="position-absolute start-50 px-2" style="bottom: -0.85rem; transform: translateX(-50%); background: var(--bs-body-bg);"><i class="bi bi-chevron-down fw-bold fs-3" style="color: #047857;"></i></span>
            </div>
            <div class="collapse mt-2" id="collapseSubmit">
                <div class="p-3"><small>Register your rental property and list the repairs or maintenance you need. It only takes a few minutes.</small></div>
            </div>
        </div>
        <div class="col-auto d-none d-md-flex align-items-center" style="margin-top: 1.2rem;">
            <i class="bi bi-arrow-right fs-3" style="color: #047857;"></i>
        </div>
        <div class="col mb-3">
            <div class="border rounded p-4 pt-3 position-relative" style="margin-top: 1.2rem; cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseReview">
                <span class="position-absolute start-50 translate-middle px-3 fw-bold fs-3" style="top: 0; background: var(--bs-body-bg); color: #047857;">2</span>
                <p class="fw-bold mb-0 mt-2">REVIEW</p>
                <span class="position-absolute start-50 px-2" style="bottom: -0.85rem; transform: translateX(-50%); background: var(--bs-body-bg);"><i class="bi bi-chevron-down fw-bold fs-3" style="color: #047857;"></i></span>
            </div>
            <div class="collapse mt-2" id="collapseReview">
                <div class="p-3"><small>We review your submission and prepare a formal letter to your landlord or agent on your behalf.</small></div>
            </div>
        </div>
        <div class="col-auto d-none d-md-flex align-items-center" style="margin-top: 1.2rem;">
            <i class="bi bi-arrow-right fs-3" style="color: #047857;"></i>
        </div>
        <div class="col mb-3">
            <div class="border rounded p-4 pt-3 position-relative" style="margin-top: 1.2rem; cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseResolve">
                <span class="position-absolute start-50 translate-middle px-3 fw-bold fs-3" style="top: 0; background: var(--bs-body-bg); color: #047857;">3</span>
                <p class="fw-bold mb-0 mt-2">RESOLVE</p>
                <span class="position-absolute start-50 px-2" style="bottom: -0.85rem; transform: translateX(-50%); background: var(--bs-body-bg);"><i class="bi bi-chevron-down fw-bold fs-3" style="color: #047857;"></i></span>
            </div>
            <div class="collapse mt-2" id="collapseResolve">
                <div class="p-3"><small>Your landlord responds and we keep you updated. A documented record you can rely on.</small></div>
            </div>
        </div>
    </div>
</div>

<hr>

<div class="text-center py-4">
    <h5 class="mb-3">BUILT FOR RENTERS</h5>
    <p>Contact your landlord without the stress!</p>
</div>

<hr>

<div class="text-center py-4">
    <h5 class="mb-3">READY TO GET STARTED?</h5>
    <a href="{{ route('register') }}" class="btn btn-lg px-5 text-white" style="background-color: #047857;">Create Your Account &mdash; Free</a>
</div>

@endsection
