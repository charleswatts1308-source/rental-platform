<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::id() !== 13 && !app()->environment('local')) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
