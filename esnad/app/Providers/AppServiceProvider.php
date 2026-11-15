<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\ConsentPolicy;
use App\Services\Otp\OtpService;
use App\Services\Otp\SmsChannel;
use App\Services\Otp\WhatsAppChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpService::class, fn () => new OtpService([
            'whatsapp' => new WhatsAppChannel,
            'sms' => new SmsChannel,
        ]));
    }

    public function boot(): void
    {
        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perMinute(5)->by('otp-send:'.$request->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-verify:'.$request->ip()),
        ]);

        Password::defaults(function () {
            $rule = Password::min(12)->letters()->numbers();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Super admins pass every permission check. Everyone else needs the permission.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Consent::class, ConsentPolicy::class);
    }
}
