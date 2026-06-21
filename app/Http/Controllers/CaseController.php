<?php

namespace App\Http\Controllers;

use App\Actions\SendCaseNotice;
use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Enums\ExhaustedStance;
use App\Enums\LandlordContactRole;
use App\Models\LandlordContact;
use App\Models\LetterTemplate;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\Setting;
use App\Services\LetterTemplateRenderer;
use App\Services\Silence\SilenceClock;
use App\Support\CaseReference;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            ->with(['property', 'landlordContact', 'category'])
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
            'landlordContact',
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

        return view('cases.show', [
            'case' => $case,
            'messages' => $messages,
            'quarantined' => $quarantined,
            'revivalExpired' => $this->dormantRevivalExpired($case),
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
    public function reply(Request $request, string $slug): RedirectResponse
    {
        $case = $this->findCaseOrFail($slug);
        $this->authorize('reply', $case);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:10000'],
        ]);

        $this->sendCaseNotice->execute(
            $case,
            actorUserId: $request->user()->id,
            tenantReplyBody: $validated['body'],
        );

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

        $case->load(['property', 'landlordContact', 'tenant']);

        $noticeNumber = $this->silenceClock->escalationCounter($case) + 1;
        $template = LetterTemplate::forEscalation($noticeNumber);
        $authorisationTemplate = LetterTemplate::query()
            ->where('type', 'ui_copy')
            ->where('code', 'escalation_authorisation')
            ->where('active', true)
            ->first();

        $vars = [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordContact->name ?: 'Sir or Madam',
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

        $properties = Property::query()
            ->where('registered_by_user_id', $request->user()->id)
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
        $payload = session(self::PREVIEW_SESSION_KEY);
        $stagedPhotoCount = 0;
        if ($payload && (int) ($payload['user_id'] ?? 0) === $request->user()->id) {
            // The staged 'validated' array is file-free (store() drops the
            // UploadedFiles), so it flashes cleanly; old() then repopulates
            // every text field. Photos can't re-seed a file input, so we
            // surface a count cue instead.
            $request->session()->flashInput($payload['validated']);
            $stagedPhotoCount = count($payload['photos'] ?? []);
        }

        return view('cases.create', [
            'properties' => $properties,
            'categories' => $categories,
            'severities' => CaseSeverity::cases(),
            'roles' => LandlordContactRole::cases(),
            'stagedPhotoCount' => $stagedPhotoCount,
        ]);
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

        $rules = [
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where('registered_by_user_id', $userId),
            ],
            'category_key' => [
                'required',
                Rule::exists('repair_categories', 'key')->where('active', true),
            ],
            'severity' => ['required', Rule::enum(CaseSeverity::class)],
            'description' => ['required', 'string', 'max:5000'],
            'landlord_email' => ['required', 'email', 'max:255'],
            'landlord_name' => ['nullable', 'string', 'max:255'],
            'landlord_role' => ['required', Rule::enum(LandlordContactRole::class)],
            'organisation_name' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];

        $validated = $request->validate($rules);

        $previewPhotos = $this->stagePreviewPhotos(
            $request->file('photos', []) ?? [],
            $userId,
        );

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

        $vars = [
            'tenant_name' => $request->user()->name,
            'landlord_name' => $validated['landlord_name'] ?? 'Sir or Madam',
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
            $contact = $this->resolveLandlordContact($validated, $userId);

            $case = RepairCase::create([
                'url_slug' => $this->mintSlug(),
                'tenant_user_id' => $userId,
                'property_id' => $validated['property_id'],
                'landlord_contact_id' => $contact->id,
                'category_key' => $validated['category_key'],
                'severity' => $validated['severity'],
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

    private function resolveLandlordContact(array $validated, int $userId): LandlordContact
    {
        $email = strtolower(trim($validated['landlord_email']));
        $existing = LandlordContact::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        return LandlordContact::create([
            'email' => $email,
            'name' => $validated['landlord_name'] ?? null,
            'role' => $validated['landlord_role'],
            'organisation_name' => $validated['organisation_name'] ?? null,
            'invited_by_user_id' => $userId,
        ]);
    }

    private function mintSlug(): string
    {
        do {
            $slug = CaseReference::generate();
        } while (RepairCase::where('url_slug', $slug)->exists());

        return $slug;
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
     * Move staged preview photos to the final cases/{case_id}/
     * folder and return the attachment input array shape that
     * SendCaseNotice expects.
     *
     * @param  array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>  $previewPhotos
     * @return array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>
     */
    private function promotePreviewPhotos(array $previewPhotos, int $caseId): array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk(self::PHOTO_DISK);
        $promoted = [];
        foreach ($previewPhotos as $photo) {
            $newPath = "cases/{$caseId}/".basename($photo['path']);
            if ($disk->exists($photo['path'])) {
                $disk->move($photo['path'], $newPath);
            }
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
