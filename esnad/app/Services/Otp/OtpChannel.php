<?php

namespace App\Services\Otp;

interface OtpChannel
{
    public function name(): string;

    /** Whether the channel has the keys it needs. */
    public function isConfigured(): bool;

    /**
     * Sends the code. Returns the provider's message ID, or throws
     * OtpDeliveryFailed so the next channel can be tried.
     */
    public function send(string $phoneE164, string $code, string $locale): ?string;
}
