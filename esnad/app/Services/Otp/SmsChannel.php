<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS fallback. Drivers:
 *  - log  writes the message to the application log (local and staging only)
 *  - http posts JSON {to, sender, text} with a bearer token to SMS_HTTP_URL,
 *         for a local gateway such as WE Business SMS once the sender ID is approved
 *
 * Firebase phone authentication sends its SMS from the browser SDK and is
 * wired in the sign-in page script when FIREBASE_* keys are set (phase 1
 * report explains the status).
 */
class SmsChannel implements OtpChannel
{
    public function name(): string
    {
        return 'sms';
    }

    public function isConfigured(): bool
    {
        return match (config('esnad.sms.driver')) {
            'log' => ! app()->isProduction(),
            'http' => filled(config('esnad.sms.http_url')) && filled(config('esnad.sms.http_token')),
            default => false,
        };
    }

    public function send(string $phoneE164, string $code, string $locale): ?string
    {
        if (! $this->isConfigured()) {
            throw new OtpDeliveryFailed('SMS is not configured.');
        }
        $text = __('auth.otp_sms', ['brand' => __('auth.brand', [], $locale), 'code' => $code, 'minutes' => intdiv(config('esnad.otp.ttl_seconds'), 60)], $locale);

        if (config('esnad.sms.driver') === 'log') {
            Log::info("[sms:log] to {$phoneE164}: {$text}");

            return null;
        }

        $response = Http::withToken(config('esnad.sms.http_token'))->timeout(10)->post(config('esnad.sms.http_url'), [
            'to' => $phoneE164,
            'sender' => config('esnad.sms.sender_id'),
            'text' => $text,
        ]);
        if ($response->failed()) {
            throw new OtpDeliveryFailed('SMS gateway error '.$response->status());
        }

        return (string) ($response->json('id') ?? '');
    }
}
