<?php

namespace App\Http\Middleware;

use App\Facades\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $duration = microtime(true) - LARAVEL_START;

        $context = [
            'method' => $request->method(),
            'duration_ms' => round($duration * 1000, 2)
        ];

        Audit::info('Incoming request', $context);
        return $next($request);
    }
}
