@extends('layouts.app')

@section('title', 'Authorise notice ' . $noticeNumber)

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h1 class="mb-0">Send the next notice?</h1>
        <a href="{{ route('cases.show', $case->url_slug) }}" class="btn btn-outline-secondary">Back to case</a>
    </div>

    <p class="lead">Your landlord has gone quiet since they last replied on case
        <code>{{ $case->url_slug }}</code>. With your go-ahead we'll send notice
        {{ $noticeNumber }} in your name.</p>

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
            <h2 class="h6 text-muted text-uppercase mb-0">Notice {{ $noticeNumber }} — preview as the landlord will see it</h2>
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
        <a href="{{ route('cases.show', $case->url_slug) }}" class="btn btn-outline-secondary">Not now</a>
        <form method="POST" action="{{ route('cases.escalate.authorise', $case->url_slug) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">Confirm and send notice {{ $noticeNumber }}</button>
        </form>
    </div>
</div>
@endsection
