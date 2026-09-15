<?php

namespace App\Http\Controllers;

use App\Actions\SendCaseNotice;
use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Enums\ExhaustedStance;
use App\Enums\LandlordContactRole;
use App\Models\LetterTemplate;
use App\Models\Property;
use App\Models\PropertyLandlordContact;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\Setting;
use App\Services\LetterTemplateRenderer;
use App\Services\Silence\SilenceClock;
use App\Support\CaseReference;
use App\Support\FileSize;
use App\Support\PhotoLimits;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tenant-facing dashboard for repair cases.
 *
 * Post Phase 3:
 *   - sendNext + reEngage demolished (D7 resolved, TAR demolished).
 *   - reply added (D8): the tenant reply surface across the full
 *     D8 availability table.
 *   - Create-case flow becomes two-step (D13): store→preview→confirm.
 *     Notice 1 is previewed before any send; description (D9) is
 *     captured here and frozen on the case as cases.description.
 *
 * Authorisation is via RepairCasePolicy: tenants only ever see and
 * act on their own cases.
 */
class CaseController extends Controller
{
    use AuthorizesRequests;

    private const PHOTO_DISK = 'local';

    private const PREVIEW_SESSION_KEY = 'cases.preview.payload';

    /**
     * Per-file upload ceiling, in kilobytes.
     *
     * A constant, not a setting (docs/attachment-policy-design.md R6): it
     * is bounded by the server's own upload_max_filesize / post_max_size,
     * and a configurable value could promise more than the box accepts —
     * which is snag #41's failure mode. The deliverability lever is the
     * COUNT, which is configurable, and later the resize option (R7).
     */
    private const PHOTO_MAX_KB = PhotoLimits::PER_FILE_KB;

    /** #69 — the staged reply awaiting its preview confirmation. */
    private const REPLY_PREVIEW_KEY = 'cases.reply_preview';

    /** #71 — one-time send tokens live under this session prefix. */
    private const SEND_TOKEN_PREFIX = 'cases.send_token.';

    /**
     * Ceiling fallback when the setting row is missing. Matches the
     * seeded default; deliberately conservative.
     */
    private const PHOTO_COUNT_DEFAULT = 1;

    public function __construct(
        private SendCaseNotice $sendCaseNotice,
        private LetterTemplateRenderer $renderer,
        private SilenceClock $silenceClock,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', RepairCase::class);

        $cases = RepairCase::query()
            ->where('tenant_user_id', $request->user()->id)
            ->with(['property', 'property.currentLandlordContact', 'category'])
            ->orderByDesc('opened_at')
            ->get();

        return view('cases.index', ['cases' => $cases]);
    }

    public function show(string $slug): View
    {
        $case = RepairCase::where('url_slug', $slug)->firstOrFail();
        $this->authorize('view', $case);

        $case->load([
            'property',
            'property.currentLandlordContact',
            'category',
            'tenant',
        ]);

        $messages = $case->messages()
            ->whereNull('quarantine_reason')
            ->orderBy('created_at')
            ->with('attachments')
            ->get();

        $quarantined = $case->messages()
            ->whereNotNull('quarantine_reason')
            ->orderBy('created_at')
            ->get();

        // #69 — a staged reply is offered back ONLY on a return via Edit
        // (?resume=1). On any other visit it is cleared, for the reason #44
        // records: a plain visit cannot otherwise be told apart from coming
        // back to finish a draft, and a stale reply would sit waiting to be
        // sent on a case the tenant has long moved on from.
        $resumeReply = null;
        $stagedReply = session(self::REPLY_PREVIEW_KEY);
        $ownsStaged = $stagedReply
            && (int) ($stagedReply['user_id'] ?? 0) === request()->user()->id
            && (int) ($stagedReply['case_id'] ?? 0) === $case->id;

        if ($ownsStaged && request()->boolean('resume')) {
            $resumeReply = $stagedReply;
        } elseif ($ownsStaged) {
            $this->discardStagedPhotos(['photos' => $stagedReply['photos'] ?? []]);
            session()->forget(self::REPLY_PREVIEW_KEY);
        }

        return view('cases.show', [
            'case' => $case,
            'messages' => $messages,
            'quarantined' => $quarantined,
            'resumeReply' => $resumeReply,
            'revivalExpired' => $this->dormantRevivalExpired($case),
            // #25 — the event that stopped the case, so the page can say
            // WHY rather than showing a status nobody can interpret. Null
            // on every case that has not been stopped by a delivery
            // failure. The cause matters: a bounce means the address can be
            // corrected, a complaint means the letter arrived and was
            // rejected (D17.5), and the two need different wording.
            'contactFailure' => $case->status === CaseStatus::ContactFailed
                ? $case->events()
                    ->whereIn('event_type', ['delivery_failed', 'delivery_complained'])
                    ->orderByDesc('id')
                    ->first()
                : null,
            // D15 — derived "tenant authorisation required" condition for
            // an engaged-then-quiet held escalation. Single source of truth
            // (SilenceClock), not a stored state (D0.3).
            'authorisationPending' => $this->silenceClock->authorisationPending($case, now()),
        ]);
    }

    /**
     * Tenant reply action (D8). Per the D8 availability table:
     *   - AwaitingTenantReview: yes (the original half-duplex snag)
     *   - AwaitingLandlord: yes (add-info)
     *   - OnHold: yes (reply IS the resume action)
     *   - Dormant: yes within dormancy.revival_days
     *   - Resolved/Abandoned: never
     * Policy enforces the availability gate; this controller delegates
     * to SendCaseNotice's $isTenantReply branch.
     */
    /**
     * #69 — stage a reply and show the tenant what will be sent.
     *
     * Charlie, 15 Sep 2026: "replies should get preview, no reason why not
     * and it makes for consistency". He is right on the consistency, and
     * there is a stronger reason than that: a reply is rendered into the
     * same letter, frozen on case_messages and served on the landlord
     * exactly like a notice. Since #19 it can carry photographs. It is
     * evidence, not chat.
     *
     * Checked against the design doc before building, as CLAUDE.md
     * requires. D8 (tenant reply) is silent on previews. D13 previews
     * letter 1 because "case creation is the one moment a preview costs
     * nothing — the tenant is present and acting", which is equally true
     * of a reply. D13 DOES reject per-letter approval, but explicitly for
     * sweep-sent escalation, on the grounds that gating automatic
     * escalation on the tenant's attention reintroduces the disease the
     * silence model cured. A letter the tenant has just written is the
     * opposite case. No conflict.
     */
    public function replyPreview(Request $request, string $slug): RedirectResponse|View
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('reply', $case);

        $rules = [
            'body' => ['required', 'string', 'min:1', 'max:10000'],
            'photos' => ['nullable', 'array', 'max:'.$this->photoCeiling()],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.self::PHOTO_MAX_KB],
        ];

        [$photoMessages, $photoAttributes] = $this->photoValidationCopy($request);
        $validated = $request->validate($rules, $photoMessages, $photoAttributes);

        // Staged, not stored straight into the case folder as the
        // pre-preview version did. A preview can be abandoned, and the
        // daily sweep already clears preview folders over 24h old (#45) —
        // so staging is what stops an abandoned reply leaving files behind
        // for ever. The promote step on confirm handles a swept file by
        // skipping and logging, exactly as the create flow does.
        $photos = $this->resolveStagedReplyPhotos($request, $case);

        session()->put(self::REPLY_PREVIEW_KEY, [
            'user_id' => $request->user()->id,
            'case_id' => $case->id,
            'body' => $validated['body'],
            'photos' => $photos,
            'staged_at' => now()->toIso8601String(),
        ]);

        $case->load(['property', 'property.currentLandlordContact', 'tenant']);

        $rendered = $this->renderer->renderFreeForm(
            $validated['body'],
            'Reply on repair case {{case_reference}} from {{tenant_name}}',
            [
                'tenant_name' => $case->tenant->name,
                'landlord_name' => $case->landlordRecipient()?->name ?: 'Sir or Madam',
                'case_reference' => $case->url_slug,
                'property_address' => $this->formatAddress($case->property),
                'issue_description' => $case->description,
            ],
        );

        return view('cases.reply-preview', [
            'case' => $case,
            'rendered' => $rendered,
            'photos' => $photos,
            'recipient' => $case->landlordRecipient(),
        ]);
    }

    /**
     * Send the previewed reply. #69 made this the CONFIRM step; it no
     * longer reads the form directly.
     */
    public function reply(Request $request, string $slug): RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('reply', $case);

        // #71 — a double-click on Send used to write TWO evidential rows
        // and post TWO letters. The form carries a one-time token minted
        // when the page rendered; the first submit consumes it and the
        // second finds nothing. Deliberately server-side: disabling the
        // button in the browser is the comfort, this is the guarantee.
        //
        // Not a validation error. The tenant pressed send once as far as
        // they are concerned, and their reply DID go — telling them
        // something failed would be false.
        if (! $this->consumeSendToken($request, 'reply', $case->id)) {
            return redirect()
                ->route('cases.show', $case->url_slug)
                ->with('success', 'Reply sent to your landlord.');
        }

        $payload = session(self::REPLY_PREVIEW_KEY);

        // Expired, cleared, or belonging to another user or another case.
        // Sending "whatever is in the session" would be how one case's
        // reply lands on another.
        if (! $payload
            || (int) ($payload['user_id'] ?? 0) !== $request->user()->id
            || (int) ($payload['case_id'] ?? 0) !== $case->id) {
            return redirect()
                ->route('cases.show', $case->url_slug)
                ->with('error', 'Your reply has expired — please write it again.');
        }

        $attachmentInputs = $this->promotePreviewPhotos($payload['photos'] ?? [], $case->id);

        $this->sendCaseNotice->execute(
            $case,
            actorUserId: $request->user()->id,
            attachmentInputs: $attachmentInputs,
            tenantReplyBody: $payload['body'],
        );

        session()->forget(self::REPLY_PREVIEW_KEY);

        return redirect()
            ->route('cases.show', $case->url_slug)
            ->with('success', 'Reply sent to your landlord.');
    }

    /**
     * D15 — preview the withheld escalation notice before authorising it.
     * Reuses the D13 preview pattern, but against the EXISTING case (no
     * session staging): renders the next escalation notice the sweep has
     * withheld, plus the escalation_authorisation ui_copy. The policy gate
     * (authoriseEscalation) confirms the case is genuinely in the
     * engaged-then-quiet held condition before anything renders.
     */
    public function escalationPreview(string $slug): View|RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('authoriseEscalation', $case);

        $case->load(['property', 'property.currentLandlordContact', 'tenant']);

        $noticeNumber = $this->silenceClock->escalationCounter($case) + 1;
        $template = LetterTemplate::forEscalation($noticeNumber);
        $authorisationTemplate = LetterTemplate::query()
            ->where('type', 'ui_copy')
            ->where('code', 'escalation_authorisation')
            ->where('active', true)
            ->first();

        $vars = [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordRecipient()?->name ?: 'Sir or Madam',
            'case_reference' => $case->url_slug,
            'property_address' => $this->formatAddress($case->property),
            'issue_description' => $case->description,
            'response_days' => (int) Setting::get('escalation.interval_days', 14),
            'notice_number' => $noticeNumber,
            'deadline_date' => null,
        ];

        return view('cases.authorise', [
            'case' => $case,
            'noticeNumber' => $noticeNumber,
            'renderedLetter' => $template ? $this->renderer->render($template, $vars) : null,
            'renderedAuthorisation' => $authorisationTemplate
                ? $this->renderer->render($authorisationTemplate, $vars)
                : null,
        ]);
    }

    /**
     * D15 — fire the withheld escalation notice. The case is in
     * awaiting_landlord, so SendCaseNotice takes its $isAutoEscalation
     * branch: this is the IDENTICAL send the sweep would have auto-fired
     * for a never-engaged landlord — counter ratchets (D3), the letter is
     * frozen in case_messages, the landlord clock restarts. No new send
     * path. The policy gate re-confirms the held condition.
     */
    public function escalationAuthorise(Request $request, string $slug): RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('authoriseEscalation', $case);

        // #71. The policy would very likely refuse a second authorisation
        // anyway, because the first send clears the held condition — but
        // "very likely" is not good enough here. A duplicate escalation
        // letter advances the ladder, the counter is DERIVED from these
        // rows and never resets (D3), and there is no way back from it.
        if (! $this->consumeSendToken($request, 'escalate', $case->id)) {
            return redirect()
                ->route('cases.show', $case->url_slug)
                ->with('success', 'The next notice has been sent to your landlord.');
        }

        $this->sendCaseNotice->execute($case, actorUserId: $request->user()->id);

        return redirect()
            ->route('cases.show', $case->url_slug)
            ->with('success', 'The next notice has been sent to your landlord.');
    }

    public function hold(Request $request, string $slug): RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('hold', $case);

        $maxDays = (int) Setting::get('hold.max_days', 60);
        $maxDate = now()->addDays($maxDays)->endOfDay();

        $validated = $request->validate([
            'hold_until' => [
                'required',
                'date',
                'after:today',
                'before_or_equal:'.$maxDate->toDateString(),
            ],
        ], [
            'hold_until.before_or_equal' => "You can pause this case for up to {$maxDays} days.",
        ]);

        $case->transitionTo(CaseStatus::OnHold, [
            'actor_user_id' => $request->user()->id,
            'actor_label' => 'tenant',
            'hold_until' => $validated['hold_until'],
        ]);

        return redirect()
            ->route('cases.show', $case->url_slug)
            ->with('success', 'Case paused until '.$case->fresh()->hold_until->format('d M Y').'.');
    }

    public function resolve(Request $request, string $slug): RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('resolve', $case);

        $case->transitionTo(CaseStatus::Resolved, [
            'actor_user_id' => $request->user()->id,
            'actor_label' => 'tenant',
        ]);

        return redirect()
            ->route('cases.show', $case->url_slug)
            ->with('success', 'Case marked resolved.');
    }

    public function abandon(Request $request, string $slug): RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('abandon', $case);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $case->transitionTo(CaseStatus::Abandoned, [
            'actor_user_id' => $request->user()->id,
            'actor_label' => 'tenant',
            'meta' => array_filter(['reason' => $validated['reason'] ?? null]),
        ]);

        return redirect()
            ->route('cases.show', $case->url_slug)
            ->with('success', 'Case marked abandoned.');
    }

    /**
     * D14 — set the cosmetic exhausted_stance label on an escalation_exhausted
     * case. This writes a display label and NOTHING mechanical: it does not
     * transitionTo, touch the clock, the ball, or any sweep input. An empty
     * submission clears the label back to unset (the tenant is never forced
     * to choose). The booted updating guard only fires on a dirty status, so
     * this plain column write passes through untouched.
     */
    public function setStance(Request $request, string $slug): RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('setStance', $case);

        $validated = $request->validate([
            'stance' => ['nullable', Rule::enum(ExhaustedStance::class)],
        ]);

        $case->exhausted_stance = $validated['stance'] ?? null;
        $case->save();

        return redirect()
            ->route('cases.show', $case->url_slug)
            ->with('success', 'Your view on this case has been saved.');
    }

    private function findCaseOrFail(string $slug): RepairCase
    {
        return RepairCase::where('url_slug', $slug)->firstOrFail();
    }

    public function create(Request $request): View
    {
        $this->authorize('create', RepairCase::class);

        // The current contact comes along so the form can SHOW the
        // landlord this case will actually be served on, rather than
        // asking for it again and then quietly ignoring the answer.
        $properties = Property::query()
            ->where('registered_by_user_id', $request->user()->id)
            ->with('currentLandlordContact')
            ->orderBy('address_line1')
            ->get();

        $categories = RepairCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        // D0.8 — returning from the preview "Edit" button lands here as a
        // plain GET. Re-flash the staged inputs so every old() in the form
        // repopulates; the file input can't be re-seeded (browser
        // security), but the staged photos survive in the session payload
        // and promote on confirm, so surface a count cue in the view.
        //
        // Snag #44 — that cue MUST NOT survive into a different case. The
        // payload is one session key per user, not per case, so without a
        // marker this method cannot tell "came back via Edit" (where the
        // cue is correct) from "starting a fresh case" (where it is a lie
        // that talks the tenant out of attaching evidence: they read "your
        // photo is saved", don't attach, and store() then overwrites the
        // payload with an empty photo array). Only ?resume=1, which only
        // the preview's Edit link sets, counts as a resume.
        $payload = session(self::PREVIEW_SESSION_KEY);
        $ownsPayload = $payload && (int) ($payload['user_id'] ?? 0) === $request->user()->id;
        $stagedPhotos = [];

        if ($ownsPayload && $request->boolean('resume')) {
            // The staged 'validated' array is file-free (store() drops the
            // UploadedFiles), so it flashes cleanly; old() then repopulates
            // every text field. Photos can't re-seed a file input, so the
            // view lists them from the payload instead — naming them, not
            // just counting them, so the tenant can see WHICH files are
            // attached rather than being told a number.
            $request->session()->flashInput($payload['validated']);
            $stagedPhotos = $payload['photos'] ?? [];
        } elseif ($ownsPayload) {
            // Starting fresh with a draft still in the session: abandon it
            // now rather than leaving it to mislead. The daily sweep would
            // clear the files after 24h anyway
            // (SilenceSweep::cleanupPreviewPhotos), but the session key
            // outlives that and is what drives the cue.
            $this->discardStagedPhotos($payload);
            $request->session()->forget(self::PREVIEW_SESSION_KEY);
        }

        return view('cases.create', [
            'properties' => $properties,
            'categories' => $categories,
            'roles' => LandlordContactRole::cases(),
            'stagedPhotos' => $stagedPhotos,
            'photoCeiling' => $this->photoCeiling(),
            'photoMaxBytes' => $this->effectivePhotoMaxBytes(),
            'photoMaxLabel' => FileSize::human($this->effectivePhotoMaxBytes()),
            'photoTotalMaxBytes' => $this->effectivePhotoTotalBytes(),
            // The form enforced the total but never stated it (#58 half
            // done): three files could each satisfy every limit the screen
            // named and still be refused as a set. The 413 page has quoted
            // this figure since #57 — the form now quotes the same one.
            'photoTotalLabel' => PhotoLimits::totalLabel(),
        ]);
    }

    /**
     * Delete the staged files behind an abandoned preview payload.
     *
     * Best-effort: the daily sweep removes orphaned preview folders after
     * 24h regardless, so a failure here costs disk space for a day, never
     * correctness.
     *
     * @param  array{photos?: array<int, array{path: string}>}  $payload
     */
    private function discardStagedPhotos(array $payload): void
    {
        $disk = Storage::disk(self::PHOTO_DISK);

        foreach ($payload['photos'] ?? [] as $photo) {
            if (isset($photo['path']) && $disk->exists($photo['path'])) {
                $disk->delete($photo['path']);
            }
        }
    }

    /**
     * D13 — step 1 of two-step create. Validate input, stash photos
     * to a per-user preview folder, stash payload in session, render
     * the preview view with the rendered notice 1 + authorisation
     * wording. No DB row exists yet; nothing is sent.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', RepairCase::class);

        $userId = $request->user()->id;

        // Model A: a property has one landlord contact, and a case
        // inherits it. When the property already has one, the landlord
        // fields are EXCLUDED rather than merely ignored — they never
        // reach $validated, so no later step can accidentally prefer a
        // typed value over the stored contact. That is the structural
        // half of snag #49: the preview and the send cannot read
        // different sources if only one source is ever present.
        $inheritsContact = $this->propertyContactFor($request->input('property_id'), $userId) !== null;

        $rules = [
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where('registered_by_user_id', $userId),
            ],
            'category_key' => [
                'required',
                Rule::exists('repair_categories', 'key')->where('active', true),
            ],
            'description' => ['required', 'string', 'max:5000'],
            'landlord_email' => [Rule::excludeIf($inheritsContact), 'required', 'email', 'max:255'],
            'landlord_name' => [Rule::excludeIf($inheritsContact), 'nullable', 'string', 'max:255'],
            'landlord_role' => [Rule::excludeIf($inheritsContact), 'required', Rule::enum(LandlordContactRole::class)],
            'organisation_name' => [Rule::excludeIf($inheritsContact), 'nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:'.$this->photoCeiling()],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.self::PHOTO_MAX_KB],
        ];

        [$photoMessages, $photoAttributes] = $this->photoValidationCopy($request);

        $validated = $request->validate($rules, $photoMessages, $photoAttributes);

        $previewPhotos = $this->resolveStagedPhotos($request, $userId);

        // The uploaded files are staged to disk above; keeping the
        // UploadedFile objects in the session payload would break
        // serialisation on any non-array session driver (file/db/redis in
        // prod). confirm() reads the staged 'photos' metadata, never
        // validated['photos'], so drop them here.
        unset($validated['photos']);

        session()->put(self::PREVIEW_SESSION_KEY, [
            'user_id' => $userId,
            'validated' => $validated,
            'photos' => $previewPhotos,
            'staged_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('cases.preview');
    }

    /**
     * D13 — preview render. Reads the staged payload from session,
     * builds the rendered notice 1 against an in-memory case+property+
     * landlord projection, plus the create_case_authorisation ui_copy
     * row.
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $this->authorize('create', RepairCase::class);

        $payload = session(self::PREVIEW_SESSION_KEY);
        if (! $payload || (int) ($payload['user_id'] ?? 0) !== $request->user()->id) {
            return redirect()->route('cases.create')->with('error', 'Your draft has expired — please re-enter the details.');
        }

        $validated = $payload['validated'];
        $property = Property::findOrFail($validated['property_id']);
        $recipient = $this->resolveLandlordFor($property, $validated);

        $vars = [
            'tenant_name' => $request->user()->name,
            // Snag #49(b) — this line used to read the typed name while
            // the send read the stored one. Both go through
            // resolveLandlordFor now, so the letter shown here is the
            // letter that leaves.
            'landlord_name' => $recipient['name'] ?: 'Sir or Madam',
            'case_reference' => '(case reference assigned on send)',
            'property_address' => $this->formatAddress($property),
            'issue_description' => $validated['description'],
            'response_days' => (int) Setting::get('escalation.interval_days', 14),
            'notice_number' => 1,
            'deadline_date' => null,
        ];

        $escalationTemplate = LetterTemplate::forEscalation(1);
        $authorisationTemplate = LetterTemplate::query()
            ->where('type', 'ui_copy')
            ->where('code', 'create_case_authorisation')
            ->where('active', true)
            ->first();

        $renderedLetter = $escalationTemplate
            ? $this->renderer->render($escalationTemplate, $vars)
            : null;
        $renderedAuthorisation = $authorisationTemplate
            ? $this->renderer->render($authorisationTemplate, $vars)
            : null;

        return view('cases.preview', [
            'payload' => $payload,
            'property' => $property,
            'renderedLetter' => $renderedLetter,
            'renderedAuthorisation' => $renderedAuthorisation,
            // Snag #59 — the address is the fact that decides whether the
            // letter arrives, and it was the one fact the preview did not
            // show. Same source the send resolves, so the two cannot
            // disagree.
            'recipient' => $recipient,
            // Snag #39 — the preview is the tenant's only chance to check
            // the letter before it reaches their landlord, and photos are
            // the part they cannot take back once sent. The data was
            // always here in the payload; it simply was not rendered.
            'stagedPhotos' => $payload['photos'] ?? [],
        ]);
    }

    /**
     * D13 — step 2 confirm. Reads the staged payload, creates the
     * case (with cases.description frozen at creation per D9), moves
     * preview photos to the final case attachment folder, fires the
     * first send via SendCaseNotice, clears the session.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('create', RepairCase::class);

        $payload = session(self::PREVIEW_SESSION_KEY);
        if (! $payload || (int) ($payload['user_id'] ?? 0) !== $request->user()->id) {
            return redirect()->route('cases.create')->with('error', 'Your draft has expired — please re-enter the details.');
        }

        $userId = $request->user()->id;
        $validated = $payload['validated'];
        $previewPhotos = $payload['photos'] ?? [];

        $case = DB::transaction(function () use ($validated, $previewPhotos, $userId) {
            $property = Property::findOrFail($validated['property_id']);

            // Inherit, or become version 1. There is no third option and
            // no per-case override: the address on the tenancy agreement
            // is the service address, and Model A models exactly one of
            // them per property.
            $propertyContact = $property->currentLandlordContact
                ?? $property->setLandlordContact([
                    'email' => strtolower(trim($validated['landlord_email'])),
                    'name' => $validated['landlord_name'] ?? null,
                    'role' => $validated['landlord_role'],
                    'organisation_name' => $validated['organisation_name'] ?? null,
                ], now(), $userId);

            $case = RepairCase::create([
                'url_slug' => $this->mintSlug(),
                'tenant_user_id' => $userId,
                'property_id' => $validated['property_id'],
                'property_landlord_contact_id' => $propertyContact->id,
                'category_key' => $validated['category_key'],
                // #50: severity is no longer collected. The column stays,
                // fixed at Routine, so nothing reading it breaks and no
                // migration destroys the values already stored.
                'severity' => CaseSeverity::Routine,
                'description' => $validated['description'],
                'status' => CaseStatus::Open,
                'current_stage' => 1,
                'opened_at' => now(),
            ]);

            $attachmentInputs = $this->promotePreviewPhotos($previewPhotos, $case->id);

            $this->sendCaseNotice->execute(
                $case,
                actorUserId: $userId,
                attachmentInputs: $attachmentInputs,
            );

            return $case;
        });

        session()->forget(self::PREVIEW_SESSION_KEY);

        return redirect()
            ->route('cases.show', $case->url_slug)
            ->with('success', 'Repair notice sent. The first letter is now on its way to your landlord.');
    }

    /**
     * The property's current landlord contact, if it has one and the
     * tenant owns the property. Null on any miss — an unowned or unknown
     * property_id simply has no contact to inherit, and the property_id
     * rule rejects it a moment later.
     */
    private function propertyContactFor(mixed $propertyId, int $userId): ?PropertyLandlordContact
    {
        if (! is_numeric($propertyId)) {
            return null;
        }

        return Property::where('id', (int) $propertyId)
            ->where('registered_by_user_id', $userId)
            ->first()
            ?->currentLandlordContact;
    }

    /**
     * The landlord this case will actually be served on.
     *
     * Snag #49(b): the preview used to render the TYPED name while the
     * send rendered the STORED one, so the tenant approved a letter that
     * was never posted. Both surfaces resolve through here now. When the
     * property carries a contact the typed fields do not even exist
     * (store() excludes them); when it does not, the typed values are
     * what version 1 will be created from. Either way there is one
     * source.
     *
     * Name AND email come back together, deliberately. Snag #59 wanted
     * the address on the preview, and resolving it separately would be
     * re-opening #49(b) in a second place — two resolvers is exactly how
     * the name and the address drift apart.
     *
     * `name` is the raw value and may be null: the preview shows the
     * address alone rather than writing "Sir or Madam" beside it, while
     * the letter applies that fallback in the salutation.
     *
     * @param  array<string, mixed>  $validated
     * @return array{name: ?string, email: string}
     */
    private function resolveLandlordFor(Property $property, array $validated): array
    {
        $contact = $property->currentLandlordContact;

        return [
            'name' => $contact
                ? $contact->name
                : ($validated['landlord_name'] ?? null),
            'email' => $contact
                ? $contact->email
                : strtolower(trim($validated['landlord_email'] ?? '')),
        ];
    }

    private function mintSlug(): string
    {
        do {
            $slug = CaseReference::generate();
        } while (RepairCase::where('url_slug', $slug)->exists());

        return $slug;
    }

    /**
     * Decide what the staged photo set is after a store() submission.
     *
     * Snag #46 — this used to be an unconditional re-stage of whatever the
     * request carried, which meant a resubmit with no files WIPED the
     * staged photos. That is the ordinary Edit round-trip: preview, spot a
     * typo, go back, fix a word, resubmit. A browser cannot re-seed a file
     * input for security reasons, so the second POST legitimately carries
     * no files — and the form was meanwhile telling the tenant "your photo
     * is saved, you don't need to re-attach it". The cue was false and the
     * photo left the letter silently.
     *
     * Three cases now, in priority order:
     *   1. New files uploaded  -> they REPLACE the staged set.
     *   2. No files, keep flag -> carry the staged set forward (the cue is
     *      now true).
     *   3. Otherwise           -> empty; the tenant removed them, or there
     *      were never any.
     *
     * The keep flag defaults to on whenever a staged set exists, so the
     * safe outcome (evidence survives) is what happens without JavaScript.
     * Only a deliberate act — choosing new files, or clicking Remove —
     * turns it off.
     *
     * @return array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>
     */
    private function resolveStagedPhotos(Request $request, int $userId): array
    {
        $incoming = $request->file('photos', []) ?? [];
        $payload = session(self::PREVIEW_SESSION_KEY);
        $ownsPayload = $payload && (int) ($payload['user_id'] ?? 0) === $userId;
        $staged = $ownsPayload ? ($payload['photos'] ?? []) : [];

        if (count($incoming) > 0) {
            // Replacing: the superseded files would be swept within 24h
            // anyway, but there is no reason to leave them lying about.
            if ($staged) {
                $this->discardStagedPhotos($payload);
            }

            return $this->stagePreviewPhotos($incoming, $userId);

        }
        // #53 — the keep instruction is PER FILE, not one boolean for the
        // set. It used to be a single flag, so Remove on one of two staged
        // photos removed both: the control said "remove this photo" and
        // the server heard "remove all photos". There was no per-file
        // identity in the instruction, so it could not have honoured a
        // partial removal even if the script had asked for one.
        //
        // Three shapes are accepted, and the ABSENT-MEANS-KEEP default
        // survives all of them, because a caller that forgets the field
        // must not be able to wipe a tenant's evidence:
        //
        //   absent          -> keep everything
        //   array of paths  -> keep exactly those, discard the rest
        //   "1" / "0"       -> the old all-or-nothing form, still honoured
        //
        if (! $request->has('keep_staged_photos')) {
            return $staged;
        }

        $keep = $request->input('keep_staged_photos');

        if (! is_array($keep)) {
            // Legacy scalar form.
            if ($request->boolean('keep_staged_photos')) {
                return $staged;
            }

            $this->discardStagedPhotos($payload);

            return [];
        }

        $keepPaths = array_filter(array_map(
            fn ($path) => is_string($path) ? $path : null,
            $keep,
        ));

        $survivors = array_values(array_filter(
            $staged,
            fn ($photo) => isset($photo['path']) && in_array($photo['path'], $keepPaths, true),
        ));

        $dropped = array_values(array_filter(
            $staged,
            fn ($photo) => ! isset($photo['path']) || ! in_array($photo['path'], $keepPaths, true),
        ));

        // Only the dropped files are deleted. The survivors have to still
        // be on disk when the letter is built.
        if ($dropped !== []) {
            $this->discardStagedPhotos(['photos' => $dropped]);
        }

        return $survivors;
    }

    /**
     * The per-file size the machine will ACTUALLY accept, in bytes.
     *
     * min(our own PHOTO_MAX_KB, PHP's upload_max_filesize). PHP rejects an
     * oversized upload before validation ever runs, so a form advertising
     * 4MB on a box configured for 2M is promising something that cannot
     * happen — the #41 lesson turned on our own UI: never display a limit
     * the machine will not honour.
     *
     * DISPLAY AND CLIENT-SIDE ONLY. The server-side validation rule stays
     * the fixed PHOTO_MAX_KB constant: the suite runs under the CLI
     * php.ini, so deriving the rule from ini_get() would make validation
     * environment-dependent. Nothing is lost by that — anything above
     * PHP's limit fires the `uploaded` rule long before `max` is reached.
     */
    private function effectivePhotoMaxBytes(): int
    {
        return PhotoLimits::perFileBytes();
    }

    /**
     * #58 — the budget for the WHOLE selection, in bytes.
     *
     * The per-file limit was never the binding constraint on a multi-photo
     * selection. PHP refuses the entire request when the multipart body
     * exceeds post_max_size, and it does so before any validation runs, so
     * the tenant loses the whole submission and the application never
     * learns it happened.
     *
     * Until now that was safe by ARITHMETIC rather than by design: a
     * ceiling of 3 and a per-file cap of 4MB comes to about 12MB against a
     * 16M post_max_size. Neither number is controlled or watched by this
     * application — post_max_size lives in the hosting panel, subscription
     * wide, and has already been changed once mid-project without the app
     * knowing. Lower it to 8M and that 12MB becomes a silent 413.
     *
     * So the sum is now checked against the real limit, with a reserve for
     * the description field, the landlord fields and multipart overhead.
     * Returns 0 when post_max_size is unreadable or unlimited, which the
     * form treats as "no total check" — the per-file rule still applies.
     */
    private function effectivePhotoTotalBytes(): int
    {
        return PhotoLimits::totalBytes();
    }

    /**
     * The live attachment ceiling for letter 1.
     *
     * Read LIVE, never snapshotted — deliberately the opposite of the
     * escalation intervals (D4), because the whole purpose of this key is
     * reacting to a deliverability problem now, across cases already
     * running (docs/attachment-policy-design.md R3).
     *
     * Clamped to the range the admin surface offers, so a value edited
     * directly in the database cannot widen the ceiling past the design.
     */
    private function photoCeiling(): int
    {
        return PhotoLimits::ceiling();
    }

    /**
     * Validation messages and attribute names for the photo rules.
     *
     * Laravel's defaults render as "The photos.0 field must not be greater
     * than 2048 kilobytes" — an internal array index, a unit nobody thinks
     * in, and form jargon, shown to a tenant attaching evidence about their
     * home. These name the tenant's own file and state the limit plainly.
     *
     * The per-index `photos.N.max` messages take precedence over the
     * wildcard, which is what lets the actual size appear; the wildcards
     * stay as the fallback so no failure is left unworded.
     *
     * Filenames are user-supplied but render through {{ $error }} in the
     * blade, which escapes.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function photoValidationCopy(Request $request): array
    {
        $ceiling = $this->photoCeiling();
        $limit = FileSize::human(self::PHOTO_MAX_KB * 1024);

        $attributes = [];
        $messages = [
            'photos.max' => $ceiling === 0
                ? 'Photos cannot be attached at the moment.'
                : 'You can attach up to '.$ceiling.' '.($ceiling === 1 ? 'photo' : 'photos').'.',
        ];

        foreach ($request->file('photos', []) ?? [] as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $label = filled($original)
                ? '"'.Str::limit($original, 60).'"'
                : 'number '.($index + 1);

            $attributes["photos.{$index}"] = $label;

            // getSize() only means anything when PHP accepted the upload.
            // On a PHP-level rejection the `uploaded` rule fires instead of
            // `max`, and that message deliberately carries no size.
            if ($file->isValid()) {
                $messages["photos.{$index}.max"] = 'Photo '.$label.' is '
                    .FileSize::human((int) $file->getSize())
                    .' — each photo must be '.$limit.' or smaller.';
            }
        }

        // Union, not merge: the per-index keys above must win.
        $messages += [
            'photos.*.max' => 'Photo :attribute is too large — each photo must be '.$limit.' or smaller.',
            'photos.*.mimes' => 'Photo :attribute is not a supported file type. Please attach a JPG, PNG or PDF.',
            'photos.*.uploaded' => 'Photo :attribute could not be uploaded — it may be too large for the server to accept.',
            'photos.*.file' => 'Photo :attribute could not be read. Please try attaching it again.',
        ];

        return [$messages, $attributes];
    }

    /**
     * Stage photos to a temp preview folder so they survive the
     * store→preview→confirm hop without being held in session.
     * The session only carries the disk paths.
     *
     * Cleanup of orphaned preview folders is handled by a daily
     * sweep — see silence:sweep's tenant tooling extensions.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>
     */
    private function stagePreviewPhotos(array $files, int $userId): array
    {
        $stored = [];
        $previewId = Str::random(20);
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->storeAs(
                "cases/preview/{$userId}/{$previewId}",
                Str::random(20).'.'.$file->getClientOriginalExtension(),
                self::PHOTO_DISK,
            );

            $stored[] = [
                'disk' => self::PHOTO_DISK,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
            ];
        }

        return $stored;
    }

    /**
     * Mint a one-time token for a form that SENDS something — #71.
     *
     * Charlie double-clicked Send on a reply (15 Sep 2026) and case
     * LYE62E took two outbound rows a second apart, and the landlord two
     * letters. Outbound rows are the evidence record; a duplicate is not
     * a cosmetic problem.
     *
     * Keyed by the token itself rather than by case, so two tabs on the
     * same case each hold a live token instead of the second render
     * invalidating the first.
     */
    public static function mintSendToken(string $action, int $caseId): string
    {
        $token = Str::random(32);
        session()->put(self::SEND_TOKEN_PREFIX.$token, $action.':'.$caseId);

        return $token;
    }

    /**
     * Consume it. True means "this is the first submit, go ahead".
     *
     * A missing token means the form was rendered before #71 shipped, or
     * the session was cleared. Treated as VALID: refusing a genuine reply
     * because a token went missing would cost a tenant their message,
     * which is worse than the duplicate this guards against. The token
     * only ever has to catch the second of two submits from the same
     * rendered page, and for that it does not need to be mandatory.
     */
    private function consumeSendToken(Request $request, string $action, int $caseId): bool
    {
        $token = (string) $request->input('send_token', '');

        if ($token === '') {
            return true;
        }

        return session()->pull(self::SEND_TOKEN_PREFIX.$token) === $action.':'.$caseId;
    }

    /**
     * Stage a reply's photos, honouring anything already staged — #69.
     *
     * The same shape as resolveStagedPhotos() for the create flow, and the
     * same two rules, because a tenant who has just used one form should
     * not find the other behaves differently:
     *
     *   choosing new files REPLACES the staged set;
     *   choosing none KEEPS what is staged.
     *
     * Absent-means-keep matters here for the same reason it does in #53: a
     * caller that forgets the field must not be able to wipe evidence.
     *
     * @return array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>
     */
    private function resolveStagedReplyPhotos(Request $request, RepairCase $case): array
    {
        $incoming = $request->file('photos', []) ?? [];
        $payload = session(self::REPLY_PREVIEW_KEY);

        $ownsPayload = $payload
            && (int) ($payload['user_id'] ?? 0) === $request->user()->id
            && (int) ($payload['case_id'] ?? 0) === $case->id;

        $staged = $ownsPayload ? ($payload['photos'] ?? []) : [];

        if (count($incoming) > 0) {
            if ($staged) {
                $this->discardStagedPhotos(['photos' => $staged]);
            }

            return $this->stagePreviewPhotos($incoming, $request->user()->id);
        }

        // #53's per-file keep instruction, on this form too. Absent means
        // keep everything; an array names exactly what survives.
        $keep = $request->input('keep_staged_photos');

        if ($keep === null) {
            return $staged;
        }

        $keepPaths = is_array($keep) ? $keep : [];
        $kept = array_values(array_filter(
            $staged,
            fn ($photo) => in_array($photo['path'] ?? '', $keepPaths, true),
        ));

        $dropped = array_values(array_filter(
            $staged,
            fn ($photo) => ! in_array($photo['path'] ?? '', $keepPaths, true),
        ));

        if ($dropped) {
            $this->discardStagedPhotos(['photos' => $dropped]);
        }

        return $kept;
    }

    /**
     * Move staged preview photos to the final cases/{case_id}/
     * folder and return the attachment input array shape that
     * SendCaseNotice expects.
     *
     * @param  array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>  $previewPhotos
     * @return array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>
     */
    private function promotePreviewPhotos(array $previewPhotos, int $caseId): array
    {
        $disk = Storage::disk(self::PHOTO_DISK);
        $promoted = [];
        foreach ($previewPhotos as $photo) {
            // Snag #45 — the staged file may be GONE. SilenceSweep's daily
            // cleanupPreviewPhotos deletes preview folders older than 24h,
            // so a draft left overnight and confirmed the next day has a
            // session payload naming files that no longer exist.
            //
            // Promoting the row anyway would write a MessageAttachment
            // pointing at nothing: CaseNotice::attachments() then calls
            // Attachment::fromStorageDisk on a missing path inside a queued
            // job, and the case page lists evidence that isn't there. Skip
            // it instead, and log — a letter quietly losing its attachment
            // must not pass unrecorded.
            if (! $disk->exists($photo['path'])) {
                Log::warning('[LLCS] Staged photo missing at promote; attachment dropped.', [
                    'case_id' => $caseId,
                    'path' => $photo['path'],
                    'original_filename' => $photo['original_filename'] ?? null,
                ]);

                continue;
            }

            $newPath = "cases/{$caseId}/".basename($photo['path']);
            $disk->move($photo['path'], $newPath);

            $promoted[] = [
                'disk' => $photo['disk'],
                'path' => $newPath,
                'original_filename' => $photo['original_filename'],
                'mime_type' => $photo['mime_type'],
                'size_bytes' => $photo['size_bytes'],
            ];
        }

        return $promoted;
    }

    private function formatAddress(Property $property): string
    {
        return implode(', ', array_filter([
            $property->address_line1,
            $property->address_line2,
            $property->city,
            $property->postcode,
        ]));
    }

    /**
     * D11 — revival window check used by the show view to decide
     * whether to render the reply form or the "raise a new case"
     * panel. The policy uses the same check to gate the reply
     * controller. Read live, not snapshotted (D0.9).
     */
    private function dormantRevivalExpired(RepairCase $case): bool
    {
        if ($case->status !== CaseStatus::Dormant || $case->dormant_at === null) {
            return false;
        }

        $revivalDays = (int) Setting::get('dormancy.revival_days', 90);

        return $case->dormant_at->copy()->addDays($revivalDays)->isPast();
    }
}
