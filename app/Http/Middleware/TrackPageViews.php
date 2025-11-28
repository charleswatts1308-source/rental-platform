<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PageView;
use Illuminate\Support\Facades\Log;

class TrackPageViews
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if page view tracking is enabled
        if (config('app.track_page_views', false)) {
            try {
                // Only track GET requests (not POST, PUT, DELETE, etc.)
                if ($request->isMethod('GET')) {
                    PageView::create([
                        'view_date_time' => now(),
                        'page_name' => $request->fullUrl(),
                        'ip_address' => $this->getClientIp($request),
                        'user_agent' => $request->userAgent() ?? 'Unknown',
                        'cookies' => json_encode($request->cookies->all()),
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't break the application
                Log::error('Page view tracking failed: ' . $e->getMessage());
            }
        }

        return $next($request);
    }

    /**
     * Get the real client IP address, checking proxy headers
     */
    private function getClientIp(Request $request): string
    {
        // Check for forwarded IP from proxy/load balancer
        // Priority order: X-Forwarded-For, X-Real-IP, then fallback to default
        if ($request->header('X-Forwarded-For')) {
            // X-Forwarded-For can contain multiple IPs, get the first one (original client)
            $ips = explode(',', $request->header('X-Forwarded-For'));
            return trim($ips[0]);
        }

        if ($request->header('X-Real-IP')) {
            return $request->header('X-Real-IP');
        }

        // Fallback to Laravel's default IP detection
        return $request->ip();
    }
}
