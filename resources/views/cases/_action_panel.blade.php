@php
    use App\Enums\CaseStatus;
    use App\Models\Setting;

    $holdMaxDays = (int) Setting::get('hold.max_days', 60);
    $holdMaxDate = now()->addDays($holdMaxDays)->toDateString();
    $revivalDays = (int) Setting::get('dormancy.revival_days', 90);
@endphp
<div class="card">
    <div class="card-body">
        <h2 class="h6 text-muted text-uppercase mb-3">Available actions</h2>

        @switch($case->status)
            @case(CaseStatus::AwaitingLandlord)
                <p class="small mb-3">Waiting on a reply from your landlord. You can send extra information at any time — sending will reset the response clock.</p>
                @break
            @case(CaseStatus::AwaitingTenantReview)
                <p class="small mb-3">Your landlord has replied. Read their message, then reply with more information, pause the case, or close it.</p>
                @break
            @case(CaseStatus::OnHold)
                <p class="small mb-3">Paused until {{ $case->hold_until?->format('d M Y') }}. Replying now resumes the case immediately.</p>
                @break
            @case(CaseStatus::Dormant)
                @if($revivalExpired ?? false)
                    <p class="small mb-3">This case has been dormant for more than {{ $revivalDays }} days. Replies are no longer accepted here — please raise a new case and quote the reference if the issue is still live.</p>
                @else
                    <p class="small mb-3">Marked dormant after a sustained period without activity. A reply within {{ $revivalDays }} days of going dormant revives the case.</p>
                @endif
                @break
            @case(CaseStatus::EscalationExhausted)
                <p class="small mb-3">The automated escalation route has run its course — your landlord did not respond to the full sequence of notices. The clock has stopped; we won't send anything further on our own. A reply from you reopens the conversation, and a late reply from your landlord still revives the case.</p>
                <a href="{{ route('members.escalation-routes') }}" class="btn btn-outline-primary btn-sm w-100 mb-3">See your options from here</a>
                @break
            @case(CaseStatus::Resolved)
                <p class="small mb-0">Marked resolved on {{ $case->closed_at?->format('d M Y') }}.</p>
                @break
            @case(CaseStatus::Abandoned)
                <p class="small mb-0">Case abandoned on {{ $case->closed_at?->format('d M Y') }}.</p>
                @break
        @endswitch

        @if($authorisationPending ?? false)
            <div class="alert alert-warning small mb-3">
                Your landlord engaged and then went quiet. We won't send the next
                formal notice without your say-so — review it and send when you're ready.
            </div>
            <a href="{{ route('cases.escalate.preview', $case->url_slug) }}" class="btn btn-primary w-100 mb-3">
                Review &amp; send the next notice
            </a>
        @endif

        @can('reply', $case)
            <form method="POST" action="{{ route('cases.reply', $case->url_slug) }}" class="mb-3">
                @csrf
                <label for="reply_body" class="form-label small">Reply to your landlord</label>
                <textarea id="reply_body" name="body" rows="4" required maxlength="10000"
                          class="form-control form-control-sm mb-2">{{ old('body') }}</textarea>
                <button type="submit" class="btn btn-primary w-100">Send reply</button>
            </form>
        @endcan

        @if($case->status === CaseStatus::Dormant && ($revivalExpired ?? false))
            <a href="{{ route('cases.create') }}" class="btn btn-outline-primary w-100 mb-3">Raise a new case</a>
        @endif

        @can('hold', $case)
            <form method="POST" action="{{ route('cases.hold', $case->url_slug) }}" class="mb-3">
                @csrf
                <label for="hold_until" class="form-label small">Pause this case until</label>
                <input id="hold_until" name="hold_until" type="date"
                       class="form-control form-control-sm mb-2"
                       min="{{ now()->addDay()->toDateString() }}"
                       max="{{ $holdMaxDate }}"
                       value="{{ old('hold_until') }}" required>
                <p class="form-text small mb-2">You can pause for up to {{ $holdMaxDays }} days.</p>
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

        {{-- #21 (D16, Option C) — the cosmetic stance dropdown is removed: it
             collided with the mechanical "Abandon this case" action above (two
             controls both reading as "abandon"). D14 is otherwise unchanged —
             an exhausted case stays revivable and closable. The ExhaustedStance
             enum and the setStance action remain in the codebase, dormant (no
             UI), pending a future disposition. --}}
    </div>
</div>
