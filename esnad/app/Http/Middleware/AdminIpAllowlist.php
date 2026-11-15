<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/** Optional IP allowlist for the admin (ADMIN_IP_ALLOWLIST, comma-separated, CIDR allowed). */
class AdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allow = config('esnad.admin.ip_allowlist');
        if ($allow !== [] && ! IpUtils::checkIp((string) $request->ip(), $allow)) {
            abort(403);
        }

        return $next($request);
    }
}
