@extends('layouts.app')

@section('title', 'Admin - Page Views')

@section('content')
<h1 class="mb-4">Page Views</h1>

<p class="text-muted mb-3">Showing {{ $pageViews->count() }} most recent page views</p>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date/Time</th>
                <th>Page</th>
                <th>IP Address</th>
                <th>User Agent</th>
                <th>Cookies</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pageViews as $view)
                <tr>
                    <td>{{ $view->page_view_id }}</td>
                    <td>{{ $view->view_date_time->format('d M Y H:i:s') }}</td>
                    <td>{{ $view->page_name }}</td>
                    <td>{{ $view->ip_address }}</td>
                    <td class="small" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $view->user_agent }}">
                        {{ $view->user_agent }}
                    </td>
                    <td class="small" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $view->cookies }}">
                        {{ $view->cookies }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">No page views found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
