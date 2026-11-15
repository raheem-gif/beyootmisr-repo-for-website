<?php

namespace App\Services\Otp;

use App\Models\DeliveryLog;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API, authentication template with a copy-code button.
 * The approved template text carries the brand name, because Egyptian
 * operators filter generic OTP wording (section 12.1).
 */
class WhatsAppChannel implements OtpChannel
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function isConfigured(): bool
    {
        return filled(config('esnad.whatsapp.token')) && filled(config('esnad.whatsapp.phone_number_id'));
    }

    public function send(string $phoneE164, string $code, string $locale): ?string
    {
        if (! $this->isConfigured()) {
            throw new OtpDeliveryFailed('WhatsApp is not configured.');
        }

        // New business accounts are limited to a number of recipients a day.
        $sentToday = DeliveryLog::query()->where('channel', 'whatsapp')->where('status', 'sent')
            ->where('created_at', '>=', now()->startOfDay())->distinct('recipient')->count('recipient');
        if ($sentToday >= config('esnad.whatsapp.daily_recipient_cap')) {
            throw new OtpDeliveryFailed('WhatsApp daily recipient cap reached.');
        }

        $cfg = config('esnad.whatsapp');
        $response = Http::withToken($cfg['token'])->timeout(10)->post(
            "https://graph.facebook.com/{$cfg['api_version']}/{$cfg['phone_number_id']}/messages",
            [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($phoneE164, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $cfg['otp_template'],
                    'language' => ['code' => $locale === 'en' ? 'en' : 'ar'],
                    'components' => [
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            throw new OtpDeliveryFailed('WhatsApp API error '.$response->status().': '.substr($response->body(), 0, 300));
        }

        return $response->json('messages.0.id');
    }
}
