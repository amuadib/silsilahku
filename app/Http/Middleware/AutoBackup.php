<?php

namespace App\Http\Middleware;

use App\Services\BackupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoBackup
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only run on GET requests to avoid interfering with form submissions
        // and only for authenticated users (or just everyone, but admin dashboard is best)
        if ($request->isMethod('GET')) {
            (new BackupService())->runAutoBackup();
        }

        return $next($request);
    }
}
