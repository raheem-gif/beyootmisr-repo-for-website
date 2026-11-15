<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Takes the locale from the first URL segment (/ar or /en). Arabic is the default. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        if (! in_array($locale, config('esnad.locales'), true)) {
            $locale = config('app.locale', 'ar');
        }
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        View::share('dir', $locale === 'ar' ? 'rtl' : 'ltr');
        // The locale is not a controller argument.
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
