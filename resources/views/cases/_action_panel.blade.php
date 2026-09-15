@php
    use App\Enums\CaseStatus;
    use App\Models\Setting;
    use App\Support\PhotoLimits;

    $holdMaxDays = (int) Setting::get('hold.max_days', 60);
    $holdMaxDate = now()->addDays($holdMaxDays)->toDateString();
    $revivalDays = (int) Setting::get('dormancy.revival_days', 90);

    // #19 — read from PhotoLimits, the same source the create form and the
    // 413 page use. Deriving them here from Setting would put a second copy
    // of the rules in a view, which is how two surfaces come to advertise
    // different limits for the same upload.
    $replyPhotoCeiling = PhotoLimits::ceiling();
    $replyPhotoMaxLabel = PhotoLimits::perFileLabel();
    $replyPhotoPerFileBytes = PhotoLimits::perFileBytes();
    $replyPhotoTotalBytes = PhotoLimits::totalBytes();
    $replyPhotoTotalLabel = PhotoLimits::totalLabel();
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
            {{-- enctype is not optional: without it the browser posts the
                 filenames and not the files, and the reply would send with
                 the tenant believing photos went with it. --}}
            <form method="POST" action="{{ route('cases.reply', $case->url_slug) }}"
                  enctype="multipart/form-data" class="mb-3">
                @csrf
                <label for="reply_body" class="form-label small">Reply to your landlord</label>
                <textarea id="reply_body" name="body" rows="4" required maxlength="10000"
                          class="form-control form-control-sm mb-2">{{ old('body') }}</textarea>

                {{-- #19. Raised in the June live-fire by a tenant wanting to
                     show a worsening problem rather than describe it, and
                     asked for again 15 Sep 2026.

                     At ceiling 0 the input is absent and SAYS SO, the same
                     as the create form: an input that simply vanishes leaves
                     a tenant with nothing to read and looks like a fault. --}}
                @if($replyPhotoCeiling > 0)
                    <label for="reply_photos" class="form-label small">Photos (optional)</label>
                    <input id="reply_photos" name="photos[]" type="file" multiple
                           accept=".jpg,.jpeg,.png,.pdf"
                           data-photo-ceiling="{{ $replyPhotoCeiling }}"
                           data-photo-max-bytes="{{ $replyPhotoPerFileBytes }}"
                           data-photo-total-max-bytes="{{ $replyPhotoTotalBytes }}"
                           class="form-control form-control-sm @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                    <div class="form-text small mb-1">
                        @include('partials.photo-limits', [
                            'ceiling' => $replyPhotoCeiling,
                            'perFileLabel' => $replyPhotoMaxLabel,
                            'totalBytes' => $replyPhotoTotalBytes,
                            'totalLabel' => $replyPhotoTotalLabel,
                        ])
                    </div>

                    {{-- Client-side problems render here, and the chosen
                         files are listed below — the same partial the create
                         form uses, so a reply cannot tell the tenant
                         something different about the same upload. --}}
                    <div id="reply-photo-errors"></div>
                    <ul id="reply-photo-list" class="list-unstyled small mt-1 mb-2"></ul>
                @else
                    <p class="form-text small mb-2">
                        Photos can&rsquo;t be attached at the moment — please describe the
                        problem in the message instead.
                    </p>
                @endif

                @foreach($errors->get('photos') as $message)
                    <div class="text-danger small mb-1">{{ $message }}</div>
                @endforeach
                @foreach($errors->get('photos.*') as $messages)
                    @foreach((array) $messages as $message)
                        <div class="text-danger small mb-1">{{ $message }}</div>
                    @endforeach
                @endforeach

                <button type="submit" class="btn btn-primary w-100">Send reply</button>
            </form>

            @if($replyPhotoCeiling > 0)
                @include('partials.photo-picker', [
                    'inputId' => 'reply_photos',
                    'listId' => 'reply-photo-list',
                    'errorsId' => 'reply-photo-errors',
                ])
            @endif
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
                {{-- "Pause", not "Hold": the internal vocabulary is hold
                     (cases.hold, hold_until, OnHold) but every tenant-facing
                     string here says pause. Only the button was leaking it. --}}
                <button type="submit" class="btn btn-outline-secondary w-100">Pause case</button>
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
