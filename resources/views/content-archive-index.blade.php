@extends('layouts.app')

@section('title', 'Content Archive')

@section('content')
<h1 class="mb-3">Content Archive</h1>

<p class="lead">Retired pages, viewable on the dev box only. Click any page to view it rendered.</p>

<p class="text-muted small mb-4">{{ $pages->count() }} pages in <code>resources/views/content-archive/</code>. Any Blade file dropped into that folder appears here automatically.</p>

<div class="list-group">
    @foreach($pages as $page)
        <a href="{{ route('content-archive.show', $page) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            {{ $page }}
            <span class="text-muted small">/content-archive/{{ $page }}</span>
        </a>
    @endforeach
</div>
@endsection
