<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Cloudflare Turnstile check for public forms. Skipped when no keys are set outside production. */
class Turnstile
{
    public static function enabled(): bool
    {
        return filled(config('esnad.turnstile.site_key')) && filled(config('esnad.turnstile.secret_key'));
    }

    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (! self::enabled()) {
            return ! app()->isProduction();
        }
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('esnad.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) $response->json('success');
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
