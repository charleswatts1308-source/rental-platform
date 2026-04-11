@extends('layouts.app')

@section('title', 'Contact Us')

@section('content')
<h1 class="mb-4">Contact Us</h1>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('contact.store') }}">
    @csrf

    <div class="mb-3">
        <label for="subject" class="form-label">Subject</label>
        <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject') }}" required>
        @error('subject')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="message" class="form-label">Message</label>
        <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message" rows="6" required>{{ old('message') }}</textarea>
        @error('message')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn text-white" style="background-color: #047857;">Send Message</button>
</form>
@endsection
