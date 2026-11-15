<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\PhoneSignInController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\NotOpenController;
use App\Http\Controllers\PageController;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Arabic is the default. A visitor whose browser prefers English and who
// has no saved choice lands on /en.
Route::get('/', function (Request $request) {
    $locale = $request->cookie('esnad_locale') ?: ($request->getPreferredLanguage(['ar', 'en']) === 'en' ? 'en' : 'ar');

    return redirect("/{$locale}", 302);
});

Route::prefix('install')->group(function () {
    Route::get('check', [InstallController::class, 'check'])->name('install.check');
    Route::get('migrate', [InstallController::class, 'migrate'])->name('install.migrate');
});

Route::view('/offline', 'offline')->name('offline');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');

        Route::get('/search', [NotOpenController::class, 'search'])->name('search');
        Route::get('/request', [NotOpenController::class, 'request'])->name('request.start');

        Route::get('/legal/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('page');

        Route::get('/design', DesignController::class)->name('design');

        Route::middleware('guest')->group(function () {
            Route::get('/sign-in', [PhoneSignInController::class, 'create'])->name('auth.sign-in');
            Route::post('/sign-in', [PhoneSignInController::class, 'store'])->middleware('throttle:otp-send')->name('auth.send');
            Route::get('/sign-in/code', [PhoneSignInController::class, 'verifyForm'])->name('auth.verify');
            Route::post('/sign-in/code', [PhoneSignInController::class, 'verify'])->middleware('throttle:otp-verify')->name('auth.verify.submit');
            Route::post('/sign-in/resend', [PhoneSignInController::class, 'resend'])->middleware('throttle:otp-send')->name('auth.resend');
        });

        Route::middleware('auth')->group(function () {
            Route::get('/welcome', [PhoneSignInController::class, 'welcomeForm'])->name('auth.welcome');
            Route::post('/welcome', [PhoneSignInController::class, 'welcomeStore'])->name('auth.welcome.store');
            Route::post('/sign-out', [PhoneSignInController::class, 'destroy'])->name('auth.sign-out');

            Route::middleware(EnsureProfileComplete::class)->group(function () {
                Route::get('/account', [AccountController::class, 'show'])->name('account');
                Route::put('/account', [AccountController::class, 'update'])->name('account.update');
                Route::post('/account/consents/{purpose}', [AccountController::class, 'giveConsent'])->name('consents.give');
                Route::delete('/account/consents/{purpose}', [AccountController::class, 'withdrawConsent'])->name('consents.withdraw');
            });
        });
    });
