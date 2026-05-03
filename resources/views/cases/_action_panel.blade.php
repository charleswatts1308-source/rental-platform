@php
    use App\Enums\CaseStatus;
@endphp
<div class="card">
    <div class="card-body">
        <h2 class="h6 text-muted text-uppercase mb-3">Available actions</h2>

        @switch($case->status)
            @case(CaseStatus::AwaitingLandlord)
                <p class="small mb-3">Waiting on a reply from your landlord. If they don't respond in time, the case will move to your action panel.</p>
                @break
            @case(CaseStatus::AwaitingTenantReview)
                <p class="small mb-3">Your landlord has replied. Read their message, then take an action.</p>
                @break
            @case(CaseStatus::TenantActionRequired)
                <p class="small mb-3">It's your move.</p>
                @break
            @case(CaseStatus::OnHold)
                <p class="small mb-3">Paused until {{ $case->hold_until?->format('d M Y') }}.</p>
                @break
            @case(CaseStatus::Dormant)
                <p class="small mb-3">No activity for 21+ days. Re-engage when you're ready to continue.</p>
                @break
            @case(CaseStatus::Resolved)
                <p class="small mb-0">Marked resolved on {{ $case->closed_at?->format('d M Y') }}.</p>
                @break
            @case(CaseStatus::Abandoned)
                <p class="small mb-0">Case abandoned on {{ $case->closed_at?->format('d M Y') }}.</p>
                @break
        @endswitch

        @can('sendNext', $case)
            <form method="POST" action="{{ route('cases.send-next', $case->url_slug) }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-primary w-100">Send the next letter</button>
            </form>
        @endcan

        @can('reEngage', $case)
            <form method="POST" action="{{ route('cases.re-engage', $case->url_slug) }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-primary w-100">Re-engage this case</button>
            </form>
        @endcan

        @can('hold', $case)
            <form method="POST" action="{{ route('cases.hold', $case->url_slug) }}" class="mb-3">
                @csrf
                <label for="hold_until" class="form-label small">Pause this case until</label>
                <input id="hold_until" name="hold_until" type="date"
                       class="form-control form-control-sm mb-2"
                       min="{{ now()->addDay()->toDateString() }}"
                       value="{{ old('hold_until') }}" required>
                <button type="submit" class="btn btn-outline-secondary w-100">Hold</button>
            </form>
        @endcan

        @can('resolve', $case)
            <form method="POST" action="{{ route('cases.resolve', $case->url_slug) }}" class="mb-2">
                @csrf
                <button type="submit" class="btn btn-outline-success w-100"
                        onclick="return confirm('Mark this case as resolved? This is final and cannot be undone.');">
                    Mark resolved
                </button>
            </form>
        @endcan

        @can('abandon', $case)
            <details class="mt-2">
                <summary class="small text-muted">Abandon this case</summary>
                <form method="POST" action="{{ route('cases.abandon', $case->url_slug) }}" class="mt-2">
                    @csrf
                    <label for="reason" class="form-label small">Reason (optional)</label>
                    <textarea id="reason" name="reason" rows="2" maxlength="2000"
                              class="form-control form-control-sm mb-2">{{ old('reason') }}</textarea>
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                            onclick="return confirm('Abandon this case? This is final and cannot be undone.');">
                        Confirm abandon
                    </button>
                </form>
            </details>
        @endcan
    </div>
</div>
