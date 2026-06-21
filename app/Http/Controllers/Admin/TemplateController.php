<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LetterTemplate;
use App\Models\LetterTextChangeHistory;
use App\Services\LetterTemplateRenderer;
use App\Services\PlaceholderValidator;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * D16 / Surface A — admin editor for letter_templates master wording.
 * Replaces phpMyAdmin for prose edits. Edits the subject + body only
 * (the wording); structural columns (code/type/stage/active) are not
 * touched here.
 *
 * Flow is edit -> preview -> confirm: the rendered preview (A3) is
 * mandatory because the edit form posts to the preview step, and only
 * the preview page can confirm the save. Every confirmed save appends a
 * letter_text_change_history row (A1).
 */
class TemplateController extends Controller
{
    public function __construct(
        private readonly LetterTemplateRenderer $renderer,
        private readonly PlaceholderValidator $placeholders,
    ) {}

    public function index(): View
    {
        $templates = LetterTemplate::query()
            ->orderBy('type')
            ->orderBy('stage')
            ->orderBy('code')
            ->get();

        return view('admin.templates.index', compact('templates'));
    }

    public function edit(LetterTemplate $letterTemplate): View
    {
        $letterTemplate->load('changeHistory.editor');

        return view('admin.templates.edit', ['template' => $letterTemplate]);
    }

    /**
     * A2 validate + A3 render. Does NOT save — shows the rendered preview
     * with a confirm button that PUTs to update().
     */
    public function preview(Request $request, LetterTemplate $letterTemplate): View
    {
        $validated = $this->validateWording($request);

        // Render the PROPOSED wording (a transient template), not the saved row.
        $proposed = new LetterTemplate([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'type' => $letterTemplate->type,
        ]);

        $rendered = $this->renderer->render($proposed, $this->sampleVars());

        return view('admin.templates.preview', [
            'template' => $letterTemplate,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'rendered' => $rendered,
        ]);
    }

    /**
     * Confirm step: re-validate (defence in depth), append a history row,
     * then commit the wording. A no-op edit writes no history.
     */
    public function update(Request $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        $validated = $this->validateWording($request);

        $unchanged = $validated['subject'] === $letterTemplate->subject
            && $validated['body'] === $letterTemplate->body;

        if ($unchanged) {
            return redirect()
                ->route('admin.templates.edit', $letterTemplate)
                ->with('success', 'No changes to save.');
        }

        $nextVersion = (int) LetterTextChangeHistory::query()
            ->where('letter_template_id', $letterTemplate->id)
            ->max('version') + 1;

        LetterTextChangeHistory::create([
            'letter_template_id' => $letterTemplate->id,
            'version' => $nextVersion,
            'edited_by_user_id' => $request->user()->id,
            'before_subject' => $letterTemplate->subject,
            'after_subject' => $validated['subject'],
            'before_body' => $letterTemplate->body,
            'after_body' => $validated['body'],
        ]);

        $letterTemplate->update([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('admin.templates.edit', $letterTemplate)
            ->with('success', "Saved. This is version {$nextVersion} of this template.");
    }

    /**
     * A2 — subject + body required; reject unknown/misspelled tokens and
     * malformed braces against the renderer's live whitelist.
     *
     * @return array{subject: string, body: string}
     */
    private function validateWording(Request $request): array
    {
        $tokenRule = function (string $attribute, mixed $value, Closure $fail): void {
            foreach ($this->placeholders->problems((string) $value) as $problem) {
                $fail($problem);
            }
        };

        return $request->validate([
            'subject' => ['required', 'string', 'max:500', $tokenRule],
            'body' => ['required', 'string', $tokenRule],
        ]);
    }

    /**
     * Sample values covering the whitelist, for the A3 preview render.
     *
     * @return array<string, string|int>
     */
    private function sampleVars(): array
    {
        return [
            'tenant_name' => 'Sample Tenant',
            'landlord_name' => 'Sample Landlord',
            'case_reference' => 'ABC234',
            'property_address' => '1 Example Street, Sampletown',
            'issue_description' => 'Sample description of the reported issue.',
            'deadline_date' => '01 Jan 2026',
            'response_days' => 14,
            'notice_number' => 1,
            'magic_link' => 'https://example.test/magic-link/sample-token',
            'last_reply_date' => '01 Jan 2026',
        ];
    }
}
