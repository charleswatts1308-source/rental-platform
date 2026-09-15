@php
    use App\Support\PhotoLimits;

    // Read from PhotoLimits, the same source the create form and the 413
    // page use. Deriving them here from Setting would put a second copy of
    // the rules in a view, which is how two surfaces come to advertise
    // different limits for the same upload.
    $replyPhotoCeiling = PhotoLimits::ceiling();
    $replyPhotoMaxLabel = PhotoLimits::perFileLabel();
    $replyPhotoPerFileBytes = PhotoLimits::perFileBytes();
    $replyPhotoTotalBytes = PhotoLimits::totalBytes();
    $replyPhotoTotalLabel = PhotoLimits::totalLabel();

    // #69 — returning from the preview via Edit. ?resume=1 is the same
    // signal the create-case Edit link carries, and for the same reason
    // (#44): a plain visit to this page must not be mistaken for coming
    // back to finish a draft, or a stale reply sits waiting to be sent on
    // a case the tenant has moved on from.
    $stagedReplyBody = $resumeReply['body'] ?? null;
    $stagedReplyPhotos = $resumeReply['photos'] ?? [];
@endphp
{{--
    The reply form, moved out of the action-panel sidebar into the main
    column on 15 Sep 2026 (#70, option b).

    It was a sidebar widget when it was a text box and a button. #19 gave
    it a file input, a limits sentence, a list of chosen files with Remove
    controls and an error area, and a third of the page is not enough room
    for that. The reading order is better here too: read the thread, then
    reply beneath it.
--}}
    @can('reply', $case)
        <h2 id="reply" class="h5 mt-4 mb-3">Reply to your landlord</h2>
        {{-- enctype is not optional: without it the browser posts the
             filenames and not the files, and the reply would send with
             the tenant believing photos went with it. --}}
        <form method="POST" action="{{ route('cases.reply.preview', $case->url_slug) }}"
              enctype="multipart/form-data" class="mb-3">
            @csrf
            {{-- No send token here: this form now leads to a preview, and
                 the token belongs on the button that actually sends (#71). --}}
            <label for="reply_body" class="form-label">Your message</label>
            <textarea id="reply_body" name="body" rows="5" required maxlength="10000"
                      class="form-control mb-2">{{ old('body', $stagedReplyBody) }}</textarea>

            {{-- #19. Raised in the June live-fire by a tenant wanting to
                 show a worsening problem rather than describe it, and
                 asked for again 15 Sep 2026.

                 At ceiling 0 the input is absent and SAYS SO, the same
                 as the create form: an input that simply vanishes leaves
                 a tenant with nothing to read and looks like a fault. --}}
            @if($replyPhotoCeiling > 0)
                <label for="reply_photos" class="form-label">Photos (optional)</label>
                <input id="reply_photos" name="photos[]" type="file" multiple
                       accept=".jpg,.jpeg,.png,.pdf"
                       data-photo-ceiling="{{ $replyPhotoCeiling }}"
                       data-photo-max-bytes="{{ $replyPhotoPerFileBytes }}"
                       data-photo-total-max-bytes="{{ $replyPhotoTotalBytes }}"
                       class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                <div class="form-text mb-1">
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
                <ul id="reply-photo-list" class="list-unstyled small mt-1 mb-3">
                    @foreach($stagedReplyPhotos as $photo)
                        {{-- #46's lesson on this form: a browser cannot re-seed
                             a file input, so coming back from the preview would
                             otherwise show an empty picker over photos that ARE
                             still attached. Each row carries its own keep
                             instruction (#53), so removing the row IS the
                             instruction. --}}
                        <li data-staged="1" class="d-flex align-items-center gap-2 mb-1">
                            <input type="hidden" name="keep_staged_photos[]" value="{{ $photo['path'] }}">
                            <span>{{ $photo['original_filename'] }}</span>
                            <span class="text-muted">({{ \App\Support\FileSize::human((int) $photo['size_bytes']) }})</span>
                            <span class="text-muted">attached</span>
                            <button type="button" data-remove-staged class="btn btn-link btn-sm p-0 text-danger">Remove</button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="form-text mb-2">
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

            <button type="submit" class="btn btn-primary">Preview reply</button>
        </form>

        @if($replyPhotoCeiling > 0)
            @include('partials.photo-picker', [
                'inputId' => 'reply_photos',
                'listId' => 'reply-photo-list',
                'errorsId' => 'reply-photo-errors',
            ])
        @endif
    @endcan
