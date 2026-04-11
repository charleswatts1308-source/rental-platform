@extends('layouts.app')

@section('title', 'Admin - Contact Messages')

@section('content')
<h1 class="mb-4">Contact Messages</h1>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<p class="text-muted mb-3">{{ $messages->count() }} messages ({{ $messages->where('replied_at', null)->count() }} unreplied)</p>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>From</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Reply</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($messages as $msg)
                <tr>
                    <td>{{ $msg->id }}</td>
                    <td class="text-nowrap">{{ $msg->created_at->format('d M Y') }}</td>
                    <td>{{ $msg->user->name }}</td>
                    <td>{{ $msg->subject }}</td>
                    <td><small>{{ Str::limit($msg->message, 80) }}</small></td>
                    <td>
                        @if($msg->replied_at)
                            <span class="badge bg-success">{{ $msg->replied_at->format('d M Y') }}</span>
                        @else
                            <span class="badge bg-warning text-dark">Pending</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.contact-messages.show', $msg->id) }}" class="btn btn-sm text-white" style="background-color: #047857;">View</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">No messages</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
