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
            {{-- Snag #59 — the address decides whether the letter arrives
                 at all, and it was the one fact this page did not show:
                 the name appeared only inside the salutation, the email
                 nowhere. #24 exists because a mistyped address was
                 permanent, and this is the last moment catching it is
                 free. Given its own block rather than a muted line beside
                 the subject, because it is the thing to CHECK, not
                 background. --}}
            <div class="alert alert-light border mb-3">
                <p class="mb-1 text-muted">This notice will be sent to:</p>
                <p class="mb-1">
                    @if($recipient['name'])
                        {{ $recipient['name'] }} &mdash;
                    @endif
                    <span class="text-break">{{ $recipient['email'] }}</span>
                </p>
                <p class="mb-0 small text-muted">
                    Please check the address. Once the notice is sent it cannot be recalled,
                    and a letter sent to the wrong address is not delivered to your landlord.
                </p>
            </div>

            @if($renderedLetter)
                <p class="small text-muted mb-2"><strong>Subject:</strong> {{ $renderedLetter['subject'] }}</p>
                <hr>
                <div>{!! $renderedLetter['body'] !!}</div>
            @else
                <div class="alert alert-warning">No active escalation template found. Seed the letter_templates table before sending.</div>
            @endif

            {{-- Snag #39 — say what is attached, and say it explicitly when
                 nothing is. A blank region reads identically to a successful
                 upload and to a silently rejected one. --}}
            <hr>
            @if(count($stagedPhotos) > 0)
                <p class="small text-muted mb-1">
                    <strong>{{ count($stagedPhotos) }}</strong>
                    {{ count($stagedPhotos) === 1 ? 'photo will be attached' : 'photos will be attached' }}
                </p>
                <ul class="small mb-0">
                    @foreach($stagedPhotos as $photo)
                        <li>
                            {{ $photo['original_filename'] ?? basename($photo['path']) }}
                            <span class="text-muted">({{ \App\Support\FileSize::human((int) ($photo['size_bytes'] ?? 0)) }})</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="small text-muted mb-0">No photos attached.</p>
            @endif
        </div>
    </div>

    <div class="d-flex justify-content-between">
        {{-- ?resume=1 marks this as a genuine return to the draft. Without
             it the create form clears the staged payload, so an abandoned
             draft cannot tell a later case that photos are already saved
             when they are not (snag #44). --}}
        <a href="{{ route('cases.create', ['resume' => 1]) }}" class="btn btn-outline-secondary">Edit</a>
        <form method="POST" action="{{ route('cases.confirm') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">Confirm and send notice 1</button>
        </form>
    </div>
</div>
@endsection
