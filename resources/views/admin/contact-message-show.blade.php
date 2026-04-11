@extends('layouts.app')

@section('title', 'Admin - View Message')

@section('content')
<h1 class="mb-4">Message from {{ $contactMessage->user->name }}</h1>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <p><strong>From:</strong> {{ $contactMessage->user->name }} ({{ $contactMessage->user->email }})</p>
        <p><strong>Date:</strong> {{ $contactMessage->created_at->format('d M Y H:i') }}</p>
        <p><strong>Subject:</strong> {{ $contactMessage->subject }}</p>
        <hr>
        <p>{!! nl2br(e($contactMessage->message)) !!}</p>
    </div>
</div>

@if($contactMessage->admin_reply)
    <div class="card mb-4">
        <div class="card-header">Your Reply (sent {{ $contactMessage->replied_at->format('d M Y H:i') }})</div>
        <div class="card-body">
            <p>{!! nl2br(e($contactMessage->admin_reply)) !!}</p>
        </div>
    </div>
@endif

@if(!$contactMessage->admin_reply)
<div class="card">
    <div class="card-header">Reply</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.contact-messages.reply', $contactMessage->id) }}">
            @csrf
            <div class="mb-3">
                <textarea class="form-control @error('admin_reply') is-invalid @enderror" name="admin_reply" rows="6" required>{{ old('admin_reply') }}</textarea>
                @error('admin_reply')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn text-white" style="background-color: #047857;">Send Reply</button>
        </form>
    </div>
</div>
@endif

<a href="{{ route('admin.contact-messages') }}" class="btn text-white mt-3" style="background-color: #047857;">Back to Messages</a>
@endsection
