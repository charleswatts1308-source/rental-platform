@extends('layouts.app')

@section('title', 'Preview notice 1')

@section('content')
<div class="container py-4">
    <h1 class="mb-3">Review and confirm</h1>

    @if($renderedAuthorisation)
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">{{ $renderedAuthorisation['subject'] }}</h2>
                {!! $renderedAuthorisation['body'] !!}
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h6 text-muted text-uppercase mb-0">Notice 1 — preview as the landlord will see it</h2>
        </div>
        <div class="card-body">
            @if($renderedLetter)
                <p class="small text-muted mb-2"><strong>Subject:</strong> {{ $renderedLetter['subject'] }}</p>
                <hr>
                <div>{!! $renderedLetter['body'] !!}</div>
            @else
                <div class="alert alert-warning">No active escalation template found. Seed the letter_templates table before sending.</div>
            @endif
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('cases.create') }}" class="btn btn-outline-secondary">Edit</a>
        <form method="POST" action="{{ route('cases.confirm') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">Confirm and send notice 1</button>
        </form>
    </div>
</div>
@endsection
