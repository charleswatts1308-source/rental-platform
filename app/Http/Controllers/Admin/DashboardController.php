<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\SilenceShadowLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin dashboard — read-only counts and the most recent registrations and
 * cases. First cut, 13 Nov 2026: at launch every registration and every
 * case is of interest, so the page leads with running totals and the 20
 * newest of each rather than trends.
 *
 * Nothing here writes. Case links go to the existing read-only oversight
 * page; state never changes from an admin screen.
 */
class DashboardController extends Controller
{
    private const RECENT = 20;

    public function index(): View
    {
        $now = Carbon::now();
        $weekAgo = $now->copy()->subDays(7);

        return view('admin.dashboard', [
            'environment' => app()->environment(),
            'users' => $this->users($weekAgo),
            'cases' => $this->cases(),
            'letters' => $this->letters($weekAgo),
            'sweeps' => $this->sweeps($now),
            'attention' => $this->attention(),
            'visitors' => $this->visitors($now),
        ]);
    }

    private function users(Carbon $weekAgo): array
    {
        $recent = User::query()
            ->select('users.*')
            ->selectSub(
                Property::query()->selectRaw('count(*)')->whereColumn('registered_by_user_id', 'users.id'),
                'properties_count'
            )
            ->selectSub(
                RepairCase::query()->selectRaw('count(*)')->whereColumn('tenant_user_id', 'users.id'),
                'cases_count'
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT)
            ->get();

        return [
            'total' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'this_week' => User::where('created_at', '>=', $weekAgo)->count(),
            'properties' => Property::count(),
            'recent' => $recent,
        ];
    }

    private function cases(): array
    {
        $byStatus = RepairCase::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        // Every status in enum order, zeros included, so the row of
        // figures has the same shape on every box.
        $statuses = [];
        foreach (CaseStatus::cases() as $status) {
            $statuses[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        $recent = RepairCase::query()
            ->with(['property', 'tenant'])
            ->withCount([
                'messages as letters_count' => fn ($q) => $q
                    ->where('direction', MessageDirection::Outbound)
                    ->where('sender_role', SenderRole::System)
                    ->whereNotNull('stage_at_send'),
                'messages as landlord_replies_count' => fn ($q) => $q
                    ->where('direction', MessageDirection::Inbound)
                    ->where('sender_role', SenderRole::Landlord),
            ])
            ->withMax('messages', 'sent_at')
            ->withMax('messages', 'received_at')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(self::RECENT)
            ->get()
            ->each(function (RepairCase $case) {
                $case->last_activity_at = collect([
                    $case->opened_at,
                    $case->messages_max_sent_at,
                    $case->messages_max_received_at,
                ])->filter()->map(fn ($d) => Carbon::parse($d))->max();
            });

        return [
            'total' => array_sum($statuses),
            'by_status' => $statuses,
            'recent' => $recent,
        ];
    }

    private function letters(Carbon $weekAgo): array
    {
        $letters = fn () => \App\Models\CaseMessage::query()
            ->where('direction', MessageDirection::Outbound)
            ->where('sender_role', SenderRole::System)
            ->whereNotNull('stage_at_send');

        $replies = fn () => \App\Models\CaseMessage::query()
            ->where('direction', MessageDirection::Inbound)
            ->where('sender_role', SenderRole::Landlord);

        return [
            'sent' => $letters()->count(),
            'sent_this_week' => $letters()->where('sent_at', '>=', $weekAgo)->count(),
            'replies' => $replies()->count(),
            'replies_this_week' => $replies()->where('received_at', '>=', $weekAgo)->count(),
        ];
    }

    /**
     * Real (non-pretend) sweep runs per day for the last 7 days, newest
     * first, zeros included. A run is a distinct swept_at.
     *
     * CAVEAT shown on the page: the sweep writes rows only for the cases it
     * evaluates, so a day with no active cases shows 0 even if the
     * scheduler fired.
     */
    private function sweeps(Carbon $now): array
    {
        $since = $now->copy()->startOfDay()->subDays(6);

        $counts = SilenceShadowLog::query()
            ->whereNull('pretend_today')
            ->where('swept_at', '>=', $since)
            ->selectRaw('DATE(swept_at) as day, COUNT(DISTINCT swept_at) as runs')
            ->groupByRaw('DATE(swept_at)')
            ->pluck('runs', 'day');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $now->copy()->startOfDay()->subDays($i)->toDateString();
            $days[$day] = (int) ($counts[$day] ?? 0);
        }

        return [
            'days' => $days,
            'last' => SilenceShadowLog::query()->whereNull('pretend_today')->max('swept_at'),
            'today' => reset($days),
            'today_ok' => reset($days) === 1,
        ];
    }

    private function attention(): array
    {
        return [
            'unanswered' => ContactMessage::query()
                ->with('user')
                ->whereNull('replied_at')
                ->orderByDesc('created_at')
                ->get(),
            'contact_failed' => RepairCase::query()
                ->with(['property', 'tenant'])
                ->where('status', CaseStatus::ContactFailed)
                ->orderByDesc('opened_at')
                ->get(),
        ];
    }

    /**
     * Visitors from our own page_views (only populated where
     * TRACK_PAGE_VIEWS=true). Crawlers excluded.
     */
    private function visitors(Carbon $now): array
    {
        $since14 = $now->copy()->startOfDay()->subDays(13);

        $perDay = PageView::query()
            ->humans()
            ->where('view_date_time', '>=', $since14)
            ->selectRaw('DATE(view_date_time) as day, COUNT(*) as views, COUNT(DISTINCT ip_address) as visitors')
            ->groupByRaw('DATE(view_date_time)')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $now->copy()->startOfDay()->subDays($i)->toDateString();
            $days[$day] = [
                'views' => (int) ($perDay[$day]->views ?? 0),
                'visitors' => (int) ($perDay[$day]->visitors ?? 0),
            ];
        }

        $topPages = PageView::query()
            ->humans()
            ->where('view_date_time', '>=', $now->copy()->subDays(30))
            ->selectRaw('page_name, COUNT(*) as views')
            ->groupBy('page_name')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        return [
            'enabled' => (bool) config('app.track_page_views', false),
            'any' => PageView::query()->exists(),
            'days' => $days,
            'top_pages' => $topPages,
        ];
    }
}
