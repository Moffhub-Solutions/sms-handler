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
    ],
    'log_channel' => env('SMS_LOG_CHANNEL', 'log'),
];
