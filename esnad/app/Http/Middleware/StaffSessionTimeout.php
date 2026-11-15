<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs staff out after 30 minutes without activity in the admin (section 13). */
class StaffSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $limit = config('esnad.admin.session_timeout_minutes') * 60;
            $last = (int) $request->session()->get('staff_last_activity', time());
            if (time() - $last > $limit) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->to('/admin/login');
            }
            $request->session()->put('staff_last_activity', time());
        }

        return $next($request);
    }
}
