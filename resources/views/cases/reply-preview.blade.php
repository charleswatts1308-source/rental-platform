@extends('layouts.app')

@section('title', 'Send this reply?')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h1 class="mb-0">Send this reply?</h1>
        <a href="{{ route('cases.show', $case->url_slug) }}" class="btn btn-outline-secondary">Back to case</a>
    </div>

    {{--
        #69. Deliberately the same shape as the create-case preview and the
        escalation authorisation: a tenant who has seen one should recognise
        this without having to read it.

        Says who it goes to as well as what it says. The reply is served on
        the property's CURRENT landlord contact, which may have been
        corrected since the case opened — so naming the address here is the
        last free moment to notice it is wrong, exactly as #59 argued for
        the create-case preview.
    --}}
    <p class="lead">
        This goes to
        <strong>{{ $recipient?->name ?: $recipient?->email }}</strong>
        @if($recipient?->name)<span class="text-muted">({{ $recipient->email }})</span>@endif
        on case <code>{{ $case->url_slug }}</code>.
    </p>

    <div class="alert alert-light border">
        <p class="mb-1">Sending restarts your landlord's response time.</p>
        <p class="mb-0 small text-muted">
            Your reply is kept on the case record exactly as sent, and cannot be
            edited afterwards.
        </p>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h6 text-muted text-uppercase mb-0">Preview as your landlord will see it</h2>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2"><strong>Subject:</strong> {{ $rendered['subject'] }}</p>
            <hr>
            {{-- Sandboxed, like the case page's own letter view: the body is
                 a full HTML document once the D9 header block is wrapped
                 round it. --}}
            <iframe srcdoc="{{ $rendered['body'] }}"
                    sandbox=""
                    style="width:100%;min-height:520px;border:1px solid #dee2e6;border-radius:4px;background:#fff;"
                    title="Your reply, as the landlord will see it"></iframe>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h6 text-muted text-uppercase mb-0">
                Attachments ({{ count($photos) }})
            </h2>
        </div>
        <div class="card-body">
            @if(count($photos) === 0)
                {{-- Said out loud rather than left blank. #39 was the
                     create-case preview showing nothing where photos were
                     attached; the mirror mistake is showing nothing where
                     none are, and letting a tenant assume theirs went. --}}
                <p class="mb-0 text-muted">No photos attached to this reply.</p>
            @else
                <ul class="list-unstyled mb-0 small">
                    @foreach($photos as $photo)
                        <li>
                            {{ $photo['original_filename'] }}
                            <span class="text-muted">({{ \App\Support\FileSize::human((int) $photo['size_bytes']) }})</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="d-flex justify-content-between">
        {{-- Edit carries ?resume=1, the same signal the create-case preview
             uses, so the case page knows to re-fill the reply rather than
             treat the visit as a fresh start (#44). --}}
        <a href="{{ route('cases.show', ['slug' => $case->url_slug, 'resume' => 1]) }}#reply"
           class="btn btn-outline-secondary">Edit</a>

        <form method="POST" action="{{ route('cases.reply', $case->url_slug) }}" class="d-inline">
            @csrf
            {{-- #71 — one-time token. A double-click on Send used to write
                 two evidential rows and post two letters. --}}
            <input type="hidden" name="send_token"
                   value="{{ \App\Http\Controllers\CaseController::mintSendToken('reply', $case->id) }}">
            <button type="submit" class="btn btn-primary">Send reply</button>
        </form>
    </div>
</div>
@endsection
