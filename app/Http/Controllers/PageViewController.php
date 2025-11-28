<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PageView;
use Illuminate\Support\Facades\DB;

class PageViewController extends Controller
{
    public function index()
    {
        // TODO: Add authorization check here later
        // if (Auth::id() !== 1) abort(403);

        $stats = [
            'total' => PageView::count(),
            'unique_ips' => PageView::distinct('ip_address')->count('ip_address'),
        ];

        // Date range
        $oldest = PageView::orderBy('view_date_time', 'asc')->first();
        $newest = PageView::orderBy('view_date_time', 'desc')->first();

        $stats['date_range'] = null;
        if ($oldest && $newest) {
            $stats['date_range'] = [
                'from' => $oldest->view_date_time,
                'to' => $newest->view_date_time,
            ];
        }

        // Top 10 pages
        $topPages = PageView::select('page_name', DB::raw('COUNT(*) as views'))
            ->groupBy('page_name')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Recent 20 views
        $recentViews = PageView::orderBy('view_date_time', 'desc')
            ->limit(20)
            ->get();

        return view('page-views.index', compact('stats', 'topPages', 'recentViews'));
    }
}
