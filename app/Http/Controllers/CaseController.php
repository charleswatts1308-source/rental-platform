<?php

namespace App\Http\Controllers;

use App\Actions\SendCaseNotice;
use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Enums\LandlordContactRole;
use App\Enums\MessageDirection;
use App\Enums\ScanStatus;
use App\Models\LandlordContact;
use App\Models\MessageAttachment;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tenant-facing dashboard for repair cases. Phase 6a covers index,
 * create, and store; Phase 6b adds the detail page and the action
 * routes (send next, hold, resolve, abandon, re-engage).
 *
 * Authorisation is via RepairCasePolicy: tenants only ever see and act
 * on their own cases. Properties offered on the create form are
 * limited to those the tenant registered themselves.
 */
class CaseController extends Controller
{
    use AuthorizesRequests;

    private const PHOTO_DISK = 'local';

    public function __construct(private SendCaseNotice $sendCaseNotice) {}

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

        return view('cases.create', [
            'properties' => $properties,
            'categories' => $categories,
            'severities' => CaseSeverity::cases(),
            'roles' => LandlordContactRole::cases(),
        ]);
    }

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
            'description' => ['nullable', 'string', 'max:5000'],
            'landlord_email' => ['required', 'email', 'max:255'],
            'landlord_name' => ['nullable', 'string', 'max:255'],
            'landlord_role' => ['required', Rule::enum(LandlordContactRole::class)],
            'organisation_name' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];

        $validated = $request->validate($rules);

        $category = RepairCategory::where('key', $validated['category_key'])->firstOrFail();
        if ($category->requires_description && empty($validated['description'] ?? null)) {
            return back()
                ->withErrors(['description' => 'A description is required for the selected category.'])
                ->withInput();
        }

        $case = DB::transaction(function () use ($validated, $request, $userId) {
            $contact = $this->resolveLandlordContact($validated, $userId);

            $case = RepairCase::create([
                'url_slug' => $this->mintSlug(),
                'tenant_user_id' => $userId,
                'property_id' => $validated['property_id'],
                'landlord_contact_id' => $contact->id,
                'category_key' => $validated['category_key'],
                'severity' => $validated['severity'],
                'status' => CaseStatus::Open,
                'current_stage' => 1,
                'opened_at' => now(),
            ]);

            $attachmentInputs = $this->savePhotos($request->file('photos', []) ?? [], $case->id);

            $message = $this->sendCaseNotice->execute(
                $case,
                $validated['description'] ?? null,
                $userId,
            );

            foreach ($attachmentInputs as $info) {
                MessageAttachment::create([
                    'case_message_id' => $message->id,
                    'disk' => self::PHOTO_DISK,
                    'path' => $info['path'],
                    'original_filename' => $info['original_filename'],
                    'mime_type' => $info['mime_type'],
                    'size_bytes' => $info['size_bytes'],
                    'direction' => MessageDirection::Outbound,
                    'scan_status' => ScanStatus::Skipped,
                ]);
            }

            return $case;
        });

        return redirect()
            ->route('cases.index')
            ->with('success', 'Repair notice sent. Case #'.$case->url_slug.' is now awaiting a landlord response.');
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
            $slug = Str::random(12);
        } while (RepairCase::where('url_slug', $slug)->exists());

        return $slug;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path: string, original_filename: string, mime_type: string, size_bytes: int}>
     */
    private function savePhotos(array $files, int $caseId): array
    {
        $stored = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->storeAs(
                "cases/{$caseId}",
                Str::random(20).'.'.$file->getClientOriginalExtension(),
                self::PHOTO_DISK,
            );

            $stored[] = [
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
            ];
        }

        return $stored;
    }
}
