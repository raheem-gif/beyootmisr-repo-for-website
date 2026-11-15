<?php

/*
|--------------------------------------------------------------------------
| ESNAD (إسناد) platform configuration
|--------------------------------------------------------------------------
|
| Values that may change without a code change live in the `settings`
| table and are edited in the admin. This file holds values that belong to
| the deployment: hosting mode, operator identity, service keys.
|
*/

return [

    'brand' => [
        'name_ar' => env('BRAND_AR', 'إسناد'),
        'name_en' => env('BRAND_EN', 'ESNAD'),
        // One accent that passes WCAG AA on white (6.09:1). The lighter logo
        // blue (#509BDF) is for the logo artwork only, never for text.
        'accent' => env('ACCENT_HEX', '#1F64A8'),
    ],

    // "shared" for cPanel shared hosting, "vps" for a Linux VPS (section 3).
    'hosting_mode' => env('APP_HOSTING_MODE', 'shared'),

    // Operator identity, shown in the footer (Anti-Cybercrime Law) and on
    // every checkout page (Consumer Protection Law, Article 37).
    'operator' => [
        'company_name' => env('COMPANY_NAME'),
        'cr_no' => env('CR_NO'),
        'tax_card_no' => env('TAX_CARD_NO'),
        'vat_no' => env('VAT_NO'),
        'office_address' => env('OFFICE_ADDRESS'),
        'support_phone' => env('SUPPORT_PHONE'),
        'support_whatsapp' => env('SUPPORT_WHATSAPP'),
        'support_email' => env('SUPPORT_EMAIL'),
        'dpo_name' => env('DPO_NAME'),
        'dpo_email' => env('DPO_EMAIL'),
        'pdpc_licence_no' => env('PDPC_LICENCE_NO'),
    ],

    'install' => [
        // Long random value. /install/check and /install/migrate answer only
        // when it is given as ?token=... . Empty disables both routes.
        'token' => env('INSTALL_TOKEN'),
    ],

    'otp' => [
        'length' => 6,
        'ttl_seconds' => 300,
        'max_attempts' => 5,
        'resend_after_seconds' => 60,
        // Per number and per IP, per hour.
        'max_sends_per_number' => 5,
        'max_sends_per_ip' => 20,
        // Ordered list of channels. The first channel that accepts the
        // message wins; the next one is a fallback.
        'channels' => array_filter(explode(',', env('OTP_CHANNELS', 'whatsapp,sms'))),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'otp_template' => env('WHATSAPP_OTP_TEMPLATE', 'esnad_otp'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        // New business accounts start at 250 recipients a day.
        'daily_recipient_cap' => (int) env('WHATSAPP_DAILY_CAP', 250),
    ],

    'sms' => [
        // "log" writes the message to the log (local and staging only).
        // "http" posts to a generic SMS gateway (Telecom Egypt WE Business
        // SMS or any provider with a JSON endpoint).
        'driver' => env('SMS_DRIVER', 'log'),
        'http_url' => env('SMS_HTTP_URL'),
        'http_token' => env('SMS_HTTP_TOKEN'),
        'sender_id' => env('SMS_SENDER_ID', 'ESNAD'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'admin' => [
        'session_timeout_minutes' => 30,
        // Comma-separated list. Empty means no allowlist.
        'ip_allowlist' => array_filter(explode(',', env('ADMIN_IP_ALLOWLIST', ''))),
    ],

    // Consent purposes required by the Personal Data Protection Law. Each is
    // shown unticked and recorded separately in the consent register.
    'consent_purposes' => ['service', 'sensitive_data', 'transfer_abroad', 'marketing'],

    // Bump when the text of a consent changes; old consents keep their version.
    'consent_version' => env('CONSENT_VERSION', '2026-11-15'),

    'locales' => ['ar', 'en'],

];
