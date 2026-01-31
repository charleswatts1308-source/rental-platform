<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PageView;
use App\Models\Rental;

class AdminController extends Controller
{
    public function users()
    {
        $totalVerified = User::whereNotNull('email_verified_at')->count();
        $totalNonVerified = User::whereNull('email_verified_at')->count();

        // Monthly non-verified counts for last 3 months
        $monthlyNonVerified = [];
        for ($i = 0; $i < 3; $i++) {
            $date = now()->startOfMonth()->subMonths($i);
            $monthlyNonVerified[] = [
                'label' => $date->format('M y'),
                'count' => User::whereNull('email_verified_at')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count()
            ];
        }

        $users = User::whereNotNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return view('admin.users', compact('users', 'totalVerified', 'totalNonVerified', 'monthlyNonVerified'));
    }

    public function pageViews()
    {
        // Common crawler/bot patterns
        $crawlerPatterns = [
            'Googlebot', 'Bingbot', 'YandexBot', 'Baiduspider', 'DuckDuckBot',
            'Slurp', 'facebot', 'ia_archiver', 'Applebot', 'AhrefsBot',
            'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot', 'Bytespider',
            'GPTBot', 'ClaudeBot', 'bot', 'crawler', 'spider'
        ];

        $totalPageViews = PageView::count();

        // Build crawler detection query
        $crawlerQuery = PageView::where(function ($query) use ($crawlerPatterns) {
            foreach ($crawlerPatterns as $pattern) {
                $query->orWhere('user_agent', 'LIKE', '%' . $pattern . '%');
            }
        });
        $crawlerPageViews = $crawlerQuery->count();
        $humanPageViews = $totalPageViews - $crawlerPageViews;

        $pageViews = PageView::orderBy('view_date_time', 'desc')
            ->limit(100)
            ->get();

        return view('admin.page-views', compact('pageViews', 'totalPageViews', 'crawlerPageViews', 'humanPageViews'));
    }

    public function rentals()
    {
        $rentals = Rental::select('rentals.*', 'users.name as user_name')
            ->join('users', 'rentals.user_id', '=', 'users.id')
            ->orderBy('date_created', 'desc')
            ->limit(100)
            ->get();

        return view('admin.rentals', compact('rentals'));
    }
}
