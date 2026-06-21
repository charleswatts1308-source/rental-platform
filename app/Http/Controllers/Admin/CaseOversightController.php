<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RepairCase;
use Illuminate\View\View;

/**
 * D16 / Surface C — read-only admin oversight of cases.
 *
 * Visibility only: state, ball, clock position, next-sweep projection, and
 * the case_events trail. There is deliberately NO force-transition and no
 * case-field editing — that would be a hole in the evidential spine (every
 * state change must have a recorded cause in case_events). A genuinely
 * stuck case is adjusted via phpMyAdmin (break-glass; the friction is the
 * safeguard).
 *
 * Display reuses the state-aware predicate on RepairCase
 * (showsNextEscalation / nextEscalationDate / showsHoldUntil) — the same
 * one the tenant case page uses, so the two can never drift (#14/#15/#21-tail).
 */
class CaseOversightController extends Controller
{
    public function index(): View
    {
        $cases = RepairCase::query()
            ->with(['property', 'tenant'])
            ->orderByDesc('opened_at')
            ->get();

        return view('admin.cases.index', compact('cases'));
    }

    public function show(RepairCase $case): View
    {
        $case->load(['property', 'tenant', 'landlordContact', 'category']);

        $events = $case->events()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.cases.show', compact('case', 'events'));
    }
}
