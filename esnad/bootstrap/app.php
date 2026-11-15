<?php

use App\Http\Middleware\LogAccess;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare. Narrow this to Cloudflare's ranges on a VPS.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [SecurityHeaders::class, LogAccess::class]);
        $middleware->encryptCookies(except: ['esnad_locale']);
        $middleware->redirectGuestsTo(fn (Request $request) => route('auth.sign-in', ['locale' => in_array($request->segment(1), ['ar', 'en']) ? $request->segment(1) : 'ar']));
        $middleware->redirectUsersTo(fn (Request $request) => route('account', ['locale' => in_array($request->segment(1), ['ar', 'en']) ? $request->segment(1) : 'ar']));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
    })->create();

// Shared hosting: deploy/build-zip.sh puts public/ in public_html beside the app folder.
if (file_exists(__DIR__.'/public-path.php')) {
    $app->usePublicPath(require __DIR__.'/public-path.php');
}

return $app;
