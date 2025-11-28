<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PageView;
use Illuminate\Support\Facades\DB;

class PageViewStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'page-views:stats
                            {--clear : Clear old page views}
                            {--days=30 : Number of days to keep (when clearing)}
                            {--limit=10 : Limit for top pages display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View page view statistics or clear old records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('clear')) {
            $this->clearOldViews();
            return 0;
        }

        $this->showStatistics();
        return 0;
    }

    /**
     * Show page view statistics
     */
    private function showStatistics()
    {
        $limit = $this->option('limit');

        $this->info('=== PAGE VIEW STATISTICS ===');
        $this->newLine();

        // Total views
        $total = PageView::count();
        $this->info("Total Page Views: {$total}");

        if ($total === 0) {
            $this->warn('No page views recorded yet.');
            return;
        }

        // Unique visitors (by IP)
        $uniqueIps = PageView::distinct('ip_address')->count('ip_address');
        $this->info("Unique Visitors (by IP): {$uniqueIps}");

        // Date range
        $oldest = PageView::orderBy('view_date_time', 'asc')->first();
        $newest = PageView::orderBy('view_date_time', 'desc')->first();
        $this->info("Date Range: {$oldest->view_date_time->format('Y-m-d H:i')} to {$newest->view_date_time->format('Y-m-d H:i')}");

        $this->newLine();

        // Top pages
        $this->info("=== TOP {$limit} PAGES ===");
        $topPages = PageView::select('page_name', DB::raw('COUNT(*) as views'))
            ->groupBy('page_name')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        $headers = ['Page', 'Views'];
        $rows = [];
        foreach ($topPages as $page) {
            $rows[] = [
                str_replace(config('app.url'), '', $page->page_name),
                $page->views
            ];
        }
        $this->table($headers, $rows);

        $this->newLine();

        // Recent views
        $this->info('=== RECENT 10 PAGE VIEWS ===');
        $recent = PageView::orderBy('view_date_time', 'desc')
            ->limit(10)
            ->get();

        $headers = ['Date/Time', 'Page', 'IP Address'];
        $rows = [];
        foreach ($recent as $view) {
            $rows[] = [
                $view->view_date_time->format('Y-m-d H:i:s'),
                str_replace(config('app.url'), '', $view->page_name),
                $view->ip_address
            ];
        }
        $this->table($headers, $rows);

        $this->newLine();
        $this->info('To clear old records: php artisan page-views:stats --clear --days=30');
    }

    /**
     * Clear old page views
     */
    private function clearOldViews()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $count = PageView::where('view_date_time', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info("No page views older than {$days} days found.");
            return;
        }

        if ($this->confirm("Delete {$count} page views older than {$days} days?")) {
            $deleted = PageView::where('view_date_time', '<', $cutoffDate)->delete();
            $this->info("Deleted {$deleted} page views.");
        } else {
            $this->info('Cancelled.');
        }
    }
}
