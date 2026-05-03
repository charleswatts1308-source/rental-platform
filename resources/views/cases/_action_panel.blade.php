{{-- Stub: action forms (send-next / hold / resolve / abandon / re-engage)
     are wired in the next Phase 6b commit. --}}
@php
    use App\Enums\CaseStatus;
@endphp
<div class="card">
    <div class="card-body">
        <h2 class="h6 text-muted text-uppercase">What happens next</h2>
        @switch($case->status)
            @case(CaseStatus::AwaitingLandlord)
                <p class="mb-0 small">Waiting on a reply from your landlord. If they don't respond in time, the case will move to your action panel.</p>
                @break
            @case(CaseStatus::AwaitingTenantReview)
                <p class="mb-0 small">Your landlord has replied. Read the message on the right, then take an action.</p>
                @break
            @case(CaseStatus::TenantActionRequired)
                <p class="mb-0 small">It's your move. Send the next letter, hold, resolve, or abandon.</p>
                @break
            @case(CaseStatus::OnHold)
                <p class="mb-0 small">Paused until {{ $case->hold_until?->format('d M Y') }}.</p>
                @break
            @case(CaseStatus::Dormant)
                <p class="mb-0 small">No activity for 21+ days. Re-engage when you're ready to continue.</p>
                @break
            @case(CaseStatus::Resolved)
                <p class="mb-0 small">Marked resolved on {{ $case->closed_at?->format('d M Y') }}.</p>
                @break
            @case(CaseStatus::Abandoned)
                <p class="mb-0 small">Case abandoned on {{ $case->closed_at?->format('d M Y') }}.</p>
                @break
            @default
                <p class="mb-0 small">First letter not yet sent.</p>
        @endswitch
    </div>
</div>
