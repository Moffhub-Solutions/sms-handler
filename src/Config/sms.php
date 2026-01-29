<?php

declare(strict_types=1);

return [
    'default' => env('SMS_PROVIDER', 'advanta'),
    'providers' => [
        'advanta' => [
            'api_key' => env('ADVANTA_API_KEY'),
            'api_url' => env('ADVANTA_API_URL'),
            'bulk_api_url' => env('ADVANTA_BULK_API_URL'),
            'partner_id' => env('ADVANTA_PARTNER_ID'),
            'short_code' => env('ADVANTA_SHORT_CODE'),
            'callback_url' => env('ADVANTA_CALLBACK_URL'),
        ],
        'at' => [
            'username' => env('AT_USERNAME', 'sandbox'),
            'api_key' => env('AT_API_KEY'),
            'from' => env('AT_FROM'),
            'api_url' => env('AT_API_URL'),
            'callback_url' => env('AT_CALLBACK_URL'),
        ],
        'onfon' => [
            'api_key' => env('ONFON_API_KEY'),
            'api_url' => env('ONFON_API_URL'),
            'sender_id' => env('ONFON_SENDER_ID'),
            'client_id' => env('ONFON_CLIENT_ID'),
            'callback_url' => env('ONFON_CALLBACK_URL'),
        ],
        'nexmo' => [
            'key' => env('NEXMO_KEY'),
            'secret' => env('NEXMO_SECRET'),
            'from' => env('NEXMO_FROM', 'NEXMO'),
            'api_url' => env('NEXMO_API_URL', 'https://rest.nexmo.com/sms/json'),
            'callback_url' => env('NEXMO_CALLBACK_URL'),
        ],

        'twilio' => [
            'account_sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'api_url' => env('TWILIO_API_URL', 'https://api.twilio.com'),
            'callback_url' => env('TWILIO_CALLBACK_URL'),
        ],
    ],
    'log_channel' => env('SMS_LOG_CHANNEL', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the webhook route for receiving delivery reports from providers.
    | Set 'enabled' to true to register webhook routes automatically.
    | The 'prefix' defines the URL prefix for webhook endpoints.
    | Webhooks are unauthenticated by default since providers can't authenticate.
    |
    */
    'webhooks' => [
        'enabled' => env('SMS_WEBHOOKS_ENABLED', false),
        'prefix' => env('SMS_WEBHOOKS_PREFIX', 'sms/webhooks'),
        'middleware' => [],
    ],
];
