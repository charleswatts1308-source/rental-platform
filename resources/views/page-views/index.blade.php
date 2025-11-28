@extends('layouts.app')

@section('title', 'Page View Statistics')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Page View Statistics</h1>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Total Page Views</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Unique Visitors</div>
                <div class="stat-value">{{ number_format($stats['unique_ips']) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Date Range</div>
                <div class="stat-value-small">
                    @if($stats['date_range'])
                        {{ $stats['date_range']['from']->format('M d, Y') }}<br>
                        to {{ $stats['date_range']['to']->format('M d, Y') }}
                    @else
                        No data yet
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($stats['total'] > 0)
        <!-- Top Pages -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Top 10 Most Visited Pages</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th class="text-end">Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topPages as $page)
                                <tr>
                                    <td>
                                        <small class="text-muted">{{ str_replace(config('app.url'), '', $page->page_name) }}</small>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-primary">{{ number_format($page->views) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Views -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent 20 Page Views</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Page</th>
                                <th>IP Address</th>
                                <th>Browser</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentViews as $view)
                                <tr>
                                    <td>
                                        <small>{{ $view->view_date_time->format('Y-m-d H:i:s') }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ str_replace(config('app.url'), '', $view->page_name) }}</small>
                                    </td>
                                    <td>
                                        <small>{{ $view->ip_address }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="{{ $view->user_agent }}">
                                            {{ Str::limit($view->user_agent, 40) }}
                                        </small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No page views recorded yet. Browse your site to start collecting data.
        </div>
    @endif
</div>

<style>
    .stat-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .stat-label {
        font-size: 0.875rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 600;
        color: #007bff;
    }

    .stat-value-small {
        font-size: 1rem;
        font-weight: 500;
        color: #495057;
        line-height: 1.4;
    }

    .card {
        border: 1px solid #ddd;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .card-header {
        background: #f8f9fa;
        border-bottom: 2px solid #007bff;
    }

    .table th {
        font-weight: 600;
        color: #495057;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table td {
        vertical-align: middle;
    }
</style>
@endsection
