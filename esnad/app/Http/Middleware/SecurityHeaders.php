<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict content security policy with a per-request nonce on public pages
 * (section 18.1). The admin panel (Filament) uses inline scripts, so it gets
 * a narrower set of headers without the script nonce rule.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $request->is('admin', 'admin/*', 'livewire/*', 'install/*')) {
            $vite = app()->isLocal() && file_exists(public_path('hot')) ? ' '.trim(file_get_contents(public_path('hot'))).' ws:' : '';
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://challenges.cloudflare.com{$vite}",
                "style-src 'self' 'unsafe-inline'{$vite}",
                "img-src 'self' data: blob:",
                "font-src 'self'",
                "connect-src 'self'{$vite}",
                'frame-src https://challenges.cloudflare.com',
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
