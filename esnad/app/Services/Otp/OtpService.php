<?php

namespace App\Services\Otp;

use App\Models\DeliveryLog;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time passwords: WhatsApp first, SMS as fallback (section 7).
 * Rate limited per number and per IP. Only a hash of the code is stored.
 */
class OtpService
{
    public const RESULT_SENT = 'sent';

    public const RESULT_THROTTLED = 'throttled';

    public const RESULT_TOO_SOON = 'too_soon';

    public const RESULT_UNDELIVERED = 'undelivered';

    /** @param  array<string, OtpChannel>  $channels */
    public function __construct(private array $channels) {}

    /** @return array{status: string, channel?: string, retry_after?: int} */
    public function send(string $phoneE164, string $purpose, string $ip, string $locale): array
    {
        $cfg = config('esnad.otp');

        $last = OtpCode::query()->where('phone', $phoneE164)->where('purpose', $purpose)->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now()) < $cfg['resend_after_seconds']) {
            return ['status' => self::RESULT_TOO_SOON, 'retry_after' => (int) ceil($cfg['resend_after_seconds'] - $last->created_at->diffInSeconds(now()))];
        }

        foreach (["otp:number:$phoneE164" => $cfg['max_sends_per_number'], "otp:ip:$ip" => $cfg['max_sends_per_ip']] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return ['status' => self::RESULT_THROTTLED, 'retry_after' => RateLimiter::availableIn($key)];
            }
        }
        RateLimiter::hit("otp:number:$phoneE164", 3600);
        RateLimiter::hit("otp:ip:$ip", 3600);

        $code = str_pad((string) random_int(0, 10 ** $cfg['length'] - 1), $cfg['length'], '0', STR_PAD_LEFT);

        // Earlier codes for the same purpose stop working.
        OtpCode::query()->where('phone', $phoneE164)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $otp = OtpCode::create([
            'phone' => $phoneE164,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'ip' => $ip,
            'expires_at' => now()->addSeconds($cfg['ttl_seconds']),
        ]);

        foreach ($cfg['channels'] as $name) {
            $channel = $this->channels[$name] ?? null;
            if (! $channel || ! $channel->isConfigured()) {
                continue;
            }
            try {
                $messageId = $channel->send($phoneE164, $code, $locale);
                $otp->update(['channel' => $name]);
                DeliveryLog::create(['channel' => $name, 'recipient' => $phoneE164, 'template' => "otp.$purpose", 'status' => 'sent', 'provider_message_id' => $messageId]);

                return ['status' => self::RESULT_SENT, 'channel' => $name];
            } catch (OtpDeliveryFailed $e) {
                DeliveryLog::create(['channel' => $name, 'recipient' => $phoneE164, 'template' => "otp.$purpose", 'status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
            }
        }

        return ['status' => self::RESULT_UNDELIVERED];
    }

    public function verify(string $phoneE164, string $purpose, string $code): bool
    {
        $otp = OtpCode::query()->where('phone', $phoneE164)->where('purpose', $purpose)->whereNull('consumed_at')->latest('id')->first();
        if (! $otp || ! $otp->isUsable()) {
            return false;
        }
        $otp->increment('attempts');
        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }
        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
