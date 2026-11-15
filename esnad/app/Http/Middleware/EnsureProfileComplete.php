<?php

namespace App\Http\Middleware;

use App\Services\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A signed-in client finishes the welcome step (name and consents) before using the account. */
class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (blank($user->name_ar) && blank($user->name_en) || ! ConsentService::has($user, 'service'))) {
            return redirect()->route('auth.welcome');
        }

        return $next($request);
    }
}
