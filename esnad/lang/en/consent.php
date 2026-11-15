<?php

return [

    'title' => 'Your consents',
    'intro' => 'Each consent is separate and none is ticked in advance. You can withdraw any of them at any time from your account page.',

    'purposes' => [
        'service' => [
            'label' => 'Use my details to run my account and bookings',
            'text' => 'Mobile number, name and governorate, so we can open the account and send booking confirmations and reminders. This consent is needed to open an account.',
        ],
        'sensitive_data' => [
            'label' => 'Process the details of my legal problem',
            'text' => 'The case type and any facts or files you write or upload. They reach only the lawyer you choose. The Personal Data Protection Law treats them as sensitive data. We ask again before any booking.',
        ],
        'transfer_abroad' => [
            'label' => 'Store my data on servers outside Egypt',
            'text' => 'Some services we use, such as hosting and messaging, run on servers outside Egypt. The privacy notice lists each provider and country.',
        ],
        'marketing' => [
            'label' => 'Receive messages about new guides and tools',
            'text' => 'At most one message a month by email or WhatsApp. Every message carries a way to stop them.',
        ],
    ],

    'required' => 'Required',
    'optional' => 'Optional',
    'service_required' => 'We need your consent to use your details to run the account before we can open it.',
    'transfer_required' => 'Our servers are currently outside Egypt, so we need your consent to store data there before we can open the account.',
    'given_on' => 'Given on :date',
    'withdrawn_on' => 'Withdrawn on :date',
    'not_given' => 'Not given',
    'give' => 'Agree',
    'withdraw' => 'Withdraw',
    'withdrawn' => 'Your consent is withdrawn.',
    'saved' => 'Your consent is recorded.',
    'history' => 'Consent history',
    'method' => [
        'web_checkbox' => 'On the website',
        'otp' => 'With a confirmation code',
        'written' => 'In writing',
        'api' => 'In the app',
    ],
    'version' => 'Text version :version',
    'withdraw_service_note' => 'Withdrawing this consent means closing the account. Use the account deletion request on the privacy page.',
    'privacy_link' => 'Read the privacy notice',

];
