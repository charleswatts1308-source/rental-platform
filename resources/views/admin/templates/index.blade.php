@extends('layouts.app')
@section('title', 'Admin - Letter Templates')
@section('content')
<h1 class="mb-4">Letter Templates</h1>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<p class="text-muted mb-3">
    Edit the master wording of outbound letters. Edits are previewed before they
    go live, validated for placeholder mistakes, and fully version-tracked.
    Changes affect future sends only — letters already sent are never altered.
</p>

<div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
        <thead>
            <tr>
                <th scope="col">Code</th>
                <th scope="col">Description</th>
                <th scope="col">Type</th>
                <th scope="col">Stage</th>
                <th scope="col">Active</th>
                <th scope="col"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($templates as $template)
                <tr>
                    <td><code>{{ $template->code }}</code></td>
                    <td>{{ $template->description }}</td>
                    <td>{{ $template->type }}</td>
                    <td>{{ $template->stage ?? '—' }}</td>
                    <td>
                        @if($template->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">No templates</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
