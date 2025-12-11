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
        ],
        'at' => [
            'api_key' => env('AT_API_KEY'),
            'api_url' => env('AT_API_URL'),
        ],
        'onfon' => [
            'api_key' => env('ONFON_API_KEY'),
            'api_url' => env('ONFON_API_URL'),
            'sender_id' => env('ONFON_SENDER_ID'),
            'client_id' => env('ONFON_CLIENT_ID'),
        ],
        'nexmo' => [
            'key' => env('NEXMO_KEY'),
            'secret' => env('NEXMO_SECRET'),
            'from' => env('NEXMO_FROM', 'NEXMO'),
            'api_url' => env('NEXMO_API_URL', 'https://rest.nexmo.com/sms/json'),
        ],

        'twilio' => [
            'account_sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'api_url' => env('TWILIO_API_URL', 'https://api.twilio.com'),
        ],
    ],
    'log_channel' => env('SMS_LOG_CHANNEL', 'log'),
];
