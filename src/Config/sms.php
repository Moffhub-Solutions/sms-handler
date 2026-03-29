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
            'fallback' => env('ADVANTA_FALLBACK_PROVIDER'),
            'rate_limit' => env('ADVANTA_RATE_LIMIT'),
            'per_segment_cost' => env('ADVANTA_PER_SEGMENT_COST', 0.0),
        ],
        'at' => [
            'username' => env('AT_USERNAME', 'sandbox'),
            'api_key' => env('AT_API_KEY'),
            'from' => env('AT_FROM'),
            'api_url' => env('AT_API_URL'),
            'base_url' => env('AT_BASE_URL'),
            'callback_url' => env('AT_CALLBACK_URL'),
            'fallback' => env('AT_FALLBACK_PROVIDER'),
            'rate_limit' => env('AT_RATE_LIMIT'),
            'per_segment_cost' => env('AT_PER_SEGMENT_COST', 0.0),
        ],
        'onfon' => [
            'api_key' => env('ONFON_API_KEY'),
            'api_url' => env('ONFON_API_URL'),
            'sender_id' => env('ONFON_SENDER_ID'),
            'client_id' => env('ONFON_CLIENT_ID'),
            'callback_url' => env('ONFON_CALLBACK_URL'),
            'fallback' => env('ONFON_FALLBACK_PROVIDER'),
            'rate_limit' => env('ONFON_RATE_LIMIT'),
            'per_segment_cost' => env('ONFON_PER_SEGMENT_COST', 0.0),
        ],
        'nexmo' => [
            'key' => env('NEXMO_KEY'),
            'secret' => env('NEXMO_SECRET'),
            'from' => env('NEXMO_FROM', 'NEXMO'),
            'api_url' => env('NEXMO_API_URL', 'https://rest.nexmo.com/sms/json'),
            'base_url' => env('NEXMO_BASE_URL', 'https://rest.nexmo.com'),
            'callback_url' => env('NEXMO_CALLBACK_URL'),
            'fallback' => env('NEXMO_FALLBACK_PROVIDER'),
            'rate_limit' => env('NEXMO_RATE_LIMIT'),
            'per_segment_cost' => env('NEXMO_PER_SEGMENT_COST', 0.0),
        ],

        'twilio' => [
            'account_sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'api_url' => env('TWILIO_API_URL', 'https://api.twilio.com'),
            'base_url' => env('TWILIO_BASE_URL', 'https://api.twilio.com'),
            'callback_url' => env('TWILIO_CALLBACK_URL'),
            'fallback' => env('TWILIO_FALLBACK_PROVIDER'),
            'rate_limit' => env('TWILIO_RATE_LIMIT'),
            'per_segment_cost' => env('TWILIO_PER_SEGMENT_COST', 0.0),
        ],
    ],
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '254'),

    'max_message_length' => env('SMS_MAX_MESSAGE_LENGTH', 918),

    'log_channel' => env('SMS_LOG_CHANNEL', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Structured Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the log channel used for structured SMS logging (sms.sent,
    | sms.failed, etc.). Set to null to use the default log channel.
    |
    */
    'log' => [
        'channel' => env('SMS_STRUCTURED_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for SMS jobs. These values are used by
    | SendSmsJob and SendBulkSmsJob for queue routing and retry behavior.
    |
    */
    'queue' => [
        'name' => env('SMS_QUEUE_NAME', 'default'),
        'timeout' => env('SMS_QUEUE_TIMEOUT', 30),
        'max_tries' => env('SMS_QUEUE_MAX_TRIES', 3),
        'backoff' => [10, 30, 90],
    ],

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
        'rate_limit' => env('SMS_WEBHOOKS_RATE_LIMIT', 60),
        'secrets' => [
            'advanta' => env('SMS_WEBHOOK_SECRET_ADVANTA'),
            'africastalking' => env('SMS_WEBHOOK_SECRET_AFRICASTALKING'),
            'onfon' => env('SMS_WEBHOOK_SECRET_ONFON'),
            'nexmo' => env('SMS_WEBHOOK_SECRET_NEXMO'),
            'twilio' => env('SMS_WEBHOOK_SECRET_TWILIO'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Templates
    |--------------------------------------------------------------------------
    |
    | Define named SMS templates with {{ variable }} interpolation syntax.
    | Templates can be a simple string or an array with a 'body' key.
    |
    | Example:
    |   'otp' => 'Your verification code is {{ code }}. Valid for {{ minutes }} minutes.',
    |   'welcome' => ['body' => 'Welcome {{ name }}! Thanks for joining.'],
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Inbound SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure inbound SMS webhook routes for receiving messages from providers.
    | Set 'enabled' to true to register inbound webhook routes automatically.
    |
    */
    'inbound' => [
        'enabled' => env('SMS_INBOUND_ENABLED', false),
        'route_prefix' => env('SMS_INBOUND_PREFIX', 'sms/inbound'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Strategy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for failed SMS sends. The 'adaptive' strategy
    | analyzes the error type and adjusts retry behavior accordingly.
    | The 'fixed' strategy uses the queue backoff values above.
    |
    */
    'retry' => [
        'strategy' => env('SMS_RETRY_STRATEGY', 'fixed'),
        'max_attempts' => env('SMS_RETRY_MAX_ATTEMPTS', 3),
        'backoff' => [
            'rate_limited' => [30, 60, 120],
            'server_error' => [10, 30, 90],
        ],
    ],

    'templates' => [
        // 'otp' => 'Your verification code is {{ code }}. Valid for {{ minutes }} minutes.',
        // 'welcome' => 'Welcome {{ name }}! Thanks for joining us.',
    ],
];
