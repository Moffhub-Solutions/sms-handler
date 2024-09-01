<?php

return [
    'default' => env('SMS_PROVIDER', 'advanta'),
    'providers' => [
        'advanta' => [
            'api_key' => env('ADVANTA_API_KEY'),
            'api_url' => env('ADVANTA_API_URL'),
        ],
        'at' => [
            'api_key' => env('AT_API_KEY'),
            'api_url' => env('AT_API_URL'),
        ],
    ],
    'log_channel' => env('SMS_LOG_CHANNEL', 'log'),
];
