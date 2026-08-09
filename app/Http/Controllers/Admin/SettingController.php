<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SettingChangeHist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * D16 / Surface B — admin editor for the `settings` rows (intervals, caps,
 * ladder lengths, and the B2 in-flight flag). Edits values only — no
 * create/delete: the keys are machinery vocabulary, not user data.
 *
 * B1: out-of-range values that would stall the sweep are hard-rejected on
 * save. B3: every changed value appends a settings_change_hist row.
 */
class SettingController extends Controller
{
    /**
     * The editable settings. `int` keys require a whole number >= 1 (a
     * smaller value would stall the sweep — B1). `flag` keys are 0/1.
     * `range` keys carry their own min/max and MAY be zero — the sweep
     * does not read them, so B1's floor does not apply.
     * Form field names can't contain dots (PHP mangles them), so each maps
     * to an underscore-safe field via fieldName().
     *
     * @return list<array{key: string, label: string, type: 'int'|'flag'|'range', min?: int, max?: int, help?: string}>
     */
    private function editableSettings(): array
    {
        return [
            ['key' => 'escalation.interval_days', 'label' => 'Days of landlord silence before each escalation', 'type' => 'int'],
            ['key' => 'escalation.max_notices', 'label' => 'Maximum number of escalation notices', 'type' => 'int'],
            ['key' => 'nudge.first_days', 'label' => 'Days before the first tenant nudge', 'type' => 'int'],
            ['key' => 'nudge.second_days', 'label' => 'Days before the second tenant nudge', 'type' => 'int'],
            ['key' => 'nudge.dormancy_days', 'label' => 'Days before a case goes dormant', 'type' => 'int'],
            ['key' => 'dormancy.revival_days', 'label' => 'Days a dormant case can still be revived', 'type' => 'int'],
            ['key' => 'hold.max_days', 'label' => 'Maximum hold duration (days)', 'type' => 'int'],
            [
                'key' => 'escalation.apply_inflight',
                'label' => 'Applies to In-flight cases',
                'type' => 'flag',
                'help' => 'When No (default), an interval change affects new cases only; cases already running keep the intervals they started under. When Yes, changes also reach in-flight cases at the next sweep.',
            ],
            [
                'key' => 'attachments.first_notice_max',
                'label' => 'Photos a tenant may attach to letter 1',
                'type' => 'range',
                'min' => 0,
                'max' => 3,
                'help' => 'A CEILING on what the tenant may choose — never an instruction to attach. '
                    .'Set to 0 to switch photo uploads off entirely; the form then explains why rather than '
                    .'silently losing the input. Chasing letters (stages 2-4) never carry attachments at any '
                    .'setting, because no one is present to choose them. A change here applies to cases '
                    .'started afterwards; photos already staged by a tenant mid-flight are always honoured.',
            ],
        ];
    }

    private function fieldName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    public function index(): View
    {
        $settings = array_map(function (array $spec): array {
            $spec['field'] = $this->fieldName($spec['key']);
            $spec['value'] = (string) (Setting::get($spec['key']) ?? '');

            return $spec;
        }, $this->editableSettings());

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $specs = $this->editableSettings();

        $rules = [];
        $messages = [
            'integer' => 'This value must be a whole number.',
            'min' => 'This value must be at least 1 — a smaller value would stall the sweep.',
            'in' => 'This value must be Yes or No.',
        ];

        foreach ($specs as $spec) {
            $field = $this->fieldName($spec['key']);

            $rules[$field] = match ($spec['type']) {
                'flag' => ['required', 'in:0,1'],
                'range' => ['required', 'integer', 'min:'.$spec['min'], 'max:'.$spec['max']],
                default => ['required', 'integer', 'min:1'],
            };

            // The generic `min` message above is about stalling the sweep,
            // which is true of the interval keys and meaningless for a
            // range key (whose floor is legitimately 0). Override both
            // bounds per-field so the message states the actual range.
            if ($spec['type'] === 'range') {
                $bounds = "This value must be between {$spec['min']} and {$spec['max']}.";
                $messages["{$field}.min"] = $bounds;
                $messages["{$field}.max"] = $bounds;
            }
        }

        $data = $request->validate($rules, $messages);

        foreach ($specs as $spec) {
            $field = $this->fieldName($spec['key']);
            $new = (string) $data[$field];
            $old = (string) (Setting::get($spec['key']) ?? '');

            if ($new === $old) {
                continue;
            }

            SettingChangeHist::create([
                'setting_key' => $spec['key'],
                'edited_by_user_id' => $request->user()->id,
                'old_value' => $old,
                'new_value' => $new,
            ]);

            Setting::updateOrCreate(['key' => $spec['key']], ['value' => $new]);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Settings saved.');
    }
}
