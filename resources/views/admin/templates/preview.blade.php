@extends('layouts.app')
@section('title', 'Admin - Preview Template')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Preview: <code>{{ $template->code }}</code></h1>
    <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-sm btn-outline-secondary">Back to edit</a>
</div>

<div class="alert alert-info">
    This is how the letter will render against sample data. Review it, then confirm
    to make the new wording live. The change is recorded in the template's history.
    Letters already sent are not affected.
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Rendered subject</label>
    <div class="border rounded p-2 bg-body-tertiary">{{ $rendered['subject'] }}</div>
</div>

<div class="mb-4">
    <label class="form-label fw-semibold">Rendered body</label>
    <iframe title="Rendered letter preview" srcdoc="{{ $rendered['body'] }}"
            style="width:100%; height:480px; border:1px solid #dee2e6; border-radius:.375rem; background:#fff;"></iframe>
</div>

<form method="POST" action="{{ route('admin.templates.update', $template) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="subject" value="{{ $subject }}">
    <textarea name="body" class="d-none" aria-hidden="true">{{ $body }}</textarea>
    <button type="submit" class="btn btn-success">Confirm and save</button>
    <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-link">Cancel</a>
</form>
@endsection
