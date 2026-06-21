@extends('layouts.app')
@section('title', 'Admin - Edit Template')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Edit template: <code>{{ $template->code }}</code></h1>
    <a href="{{ route('admin.templates.index') }}" class="btn btn-sm btn-outline-secondary">Back to list</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Could not preview these changes:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p class="text-muted">{{ $template->description }} — type <strong>{{ $template->type }}</strong>@if($template->stage), stage {{ $template->stage }}@endif.</p>

<form method="POST" action="{{ route('admin.templates.preview', $template) }}">
    @csrf

    <div class="mb-3">
        <label for="subject" class="form-label">Subject</label>
        <input type="text" class="form-control" id="subject" name="subject"
               value="{{ old('subject', $template->subject) }}" maxlength="500" required>
    </div>

    <div class="mb-3">
        <label for="body" class="form-label">Body</label>
        <textarea class="form-control font-monospace" id="body" name="body" rows="18" required>{{ old('body', $template->body) }}</textarea>
        <div class="form-text">
            Placeholders use <code>&#123;&#123;name&#125;&#125;</code> syntax. Allowed fields:
            {{ implode(', ', \App\Services\LetterTemplateRenderer::WHITELIST) }}.
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Preview changes</button>
</form>

@if($template->changeHistory->isNotEmpty())
    <hr class="my-4">
    <h2 class="h5">Change history</h2>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th scope="col">Version</th>
                    <th scope="col">Edited by</th>
                    <th scope="col">When</th>
                </tr>
            </thead>
            <tbody>
                @foreach($template->changeHistory as $change)
                    <tr>
                        <td>{{ $change->version }}</td>
                        <td>{{ $change->editor?->name ?? 'Unknown' }}</td>
                        <td>{{ $change->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
