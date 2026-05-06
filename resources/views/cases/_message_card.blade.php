@php
    use App\Enums\MessageDirection;
    $isInbound = $message->direction === MessageDirection::Inbound;
    $headerClass = $isInbound ? 'bg-light' : 'bg-primary text-white';
    $sentAt = $message->received_at ?? $message->sent_at ?? $message->created_at;
@endphp
<div class="card mb-3">
    <div class="card-header {{ $headerClass }}">
        <strong>{{ $isInbound ? 'From landlord' : 'Letter sent' }}</strong>
        @if($message->stage_at_send)<span class="badge bg-secondary ms-2">Stage {{ $message->stage_at_send }}</span>@endif
        <span class="float-end small">{{ $sentAt?->format('d M Y, H:i') }}</span>
    </div>
    <div class="card-body">
        @if($message->subject)
            <p class="fw-bold mb-2">{{ $message->subject }}</p>
        @endif

        @if($isInbound)
            <div class="message-body">{!! $message->body_sanitised !!}</div>
        @else
            @if($message->tenant_statement)
                <div class="border-start border-3 ps-3 mb-3">
                    <small class="text-muted d-block">Your description (included in the letter)</small>
                    {{ $message->tenant_statement }}
                </div>
            @endif
            @if($message->body_raw)
                <details class="mt-2">
                    <summary class="small text-muted" style="cursor:pointer;">View the letter sent to your landlord</summary>
                    <iframe srcdoc="{{ $message->body_raw }}"
                            sandbox=""
                            style="width:100%;min-height:520px;border:1px solid #dee2e6;border-radius:4px;margin-top:0.75rem;background:#fff;"
                            title="Outbound letter, stage {{ $message->stage_at_send }}"></iframe>
                </details>
            @else
                <p class="text-muted small mb-0">A formal repair letter was sent to your landlord. The full text is recorded for the audit trail.</p>
            @endif
        @endif

        @if($message->attachments->isNotEmpty())
            <div class="mt-3">
                <small class="text-muted d-block mb-1">Attachments</small>
                <ul class="list-unstyled mb-0 small">
                    @foreach($message->attachments as $attachment)
                        <li>{{ $attachment->original_filename ?? basename($attachment->path) }} <span class="text-muted">({{ number_format($attachment->size_bytes / 1024, 0) }} KB)</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
