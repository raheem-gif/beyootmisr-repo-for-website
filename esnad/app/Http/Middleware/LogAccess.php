<?php

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Identity, traffic and device data for 180 days (Anti-Cybercrime Law,
 * Article 2). Stores the route name or path template, never query strings.
 */
class LogAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->is('up', 'build/*', 'fonts/*', 'brand/*', 'sw.js', 'manifest.webmanifest')) {
            return;
        }
        try {
            $route = $request->route();
            AccessLog::create([
                'user_id' => $request->user()?->getKey(),
                'ip' => (string) $request->ip(),
                'method' => $request->method(),
                'route' => substr($route?->getName() ?? $route?->uri() ?? $request->path(), 0, 191),
                'status' => $response->getStatusCode(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'device_id' => substr((string) $request->cookie('esnad_device'), 0, 64) ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
