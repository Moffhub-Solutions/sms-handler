## SMS Handler

[![Latest Version on Packagist](https://img.shields.io/packagist/v/moffhub/sms-handler.svg?style=flat-square)](https://packagist.org/packages/moffhub/sms-handler)
[![Total Downloads](https://img.shields.io/packagist/dt/moffhub/sms-handler.svg?style=flat-square)](https://packagist.org/packages/moffhub/sms-handler)

A simple, unified SMS integration library for Laravel. Send SMS messages through multiple providers with a consistent API, automatic fallback, rate limiting, templating, cost estimation, and analytics.

## Features

- [x] Send SMS, Bulk SMS, and Scheduled SMS
- [x] Multiple provider support (Advanta, Africa's Talking, Twilio, Nexmo, Onfon Media)
- [x] Custom provider extensibility
- [x] Automatic fallback provider chain
- [x] Per-provider rate limiting
- [x] SMS templating with variable interpolation
- [x] Cost estimation and segment counting
- [x] Analytics and success rate tracking
- [x] Webhook delivery report handling with signature validation
- [x] Events (SmsSent, SmsFailed, DeliveryReportReceived)
- [x] Structured logging with credential scrubbing
- [x] Laravel Notification channel support
- [x] Phone number and message validation
- [x] Database or file logging

## Supported Providers

- **Advanta** - Kenya SMS gateway
- **Africa's Talking** - Pan-African SMS gateway
- **Twilio** - Global SMS provider
- **Nexmo/Vonage** - Global SMS provider
- **Onfon Media** - Kenya SMS gateway
- **Custom** - Build your own provider

## Installation

```bash
composer require moffhub/sms-handler
```

## Configuration

Publish the config and migrations:

```bash
php artisan vendor:publish --provider="Moffhub\SmsHandler\SmsHandlerServiceProvider" --tag=sms-config
php artisan vendor:publish --tag=sms-migrations
php artisan migrate
```

### Environment Variables

Add the following to your `.env` file based on your provider:

```bash
# Provider selection
SMS_PROVIDER=at  # Options: advanta, at, onfon, twilio, nexmo

# Africa's Talking
AT_USERNAME=sandbox          # Use 'sandbox' for testing, your app username for production
AT_API_KEY=your_api_key
AT_FROM=YOUR_SENDER_ID       # Optional: Your registered sender ID/short code
AT_API_URL=                  # Optional: Custom API URL (auto-detected based on username)
AT_FALLBACK_PROVIDER=        # Optional: Fallback provider name (e.g., 'advanta')
AT_RATE_LIMIT=               # Optional: Messages per minute (null = unlimited)
AT_PER_SEGMENT_COST=0.80     # Optional: Cost per SMS segment

# Advanta
ADVANTA_API_KEY=
ADVANTA_API_URL=
ADVANTA_BULK_API_URL=
ADVANTA_PARTNER_ID=
ADVANTA_SHORT_CODE=
ADVANTA_FALLBACK_PROVIDER=
ADVANTA_RATE_LIMIT=
ADVANTA_PER_SEGMENT_COST=1.50

# Onfon Media
ONFON_API_KEY=
ONFON_API_URL=
ONFON_SENDER_ID=
ONFON_CLIENT_ID=
ONFON_FALLBACK_PROVIDER=
ONFON_RATE_LIMIT=
ONFON_PER_SEGMENT_COST=

# Nexmo/Vonage
NEXMO_KEY=
NEXMO_SECRET=
NEXMO_FROM=NEXMO
NEXMO_API_URL=https://rest.nexmo.com/sms/json
NEXMO_FALLBACK_PROVIDER=
NEXMO_RATE_LIMIT=
NEXMO_PER_SEGMENT_COST=

# Twilio
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
TWILIO_API_URL=https://api.twilio.com
TWILIO_FALLBACK_PROVIDER=
TWILIO_RATE_LIMIT=
TWILIO_PER_SEGMENT_COST=

# Logging
SMS_LOG_CHANNEL=log  # Options: log, model
SMS_STRUCTURED_LOG_CHANNEL=  # Optional: Separate log channel for structured SMS logs

# Webhooks
SMS_WEBHOOKS_ENABLED=false
SMS_WEBHOOKS_PREFIX=sms/webhooks
SMS_WEBHOOKS_RATE_LIMIT=60
SMS_WEBHOOK_SECRET_ADVANTA=
SMS_WEBHOOK_SECRET_AFRICASTALKING=
SMS_WEBHOOK_SECRET_ONFON=
SMS_WEBHOOK_SECRET_NEXMO=
SMS_WEBHOOK_SECRET_TWILIO=

# Queue
SMS_QUEUE_NAME=default
SMS_QUEUE_TIMEOUT=30
SMS_QUEUE_MAX_TRIES=3
```

## Usage

### Using the Facade

```php
use Moffhub\SmsHandler\Facades\Sms;

// Send a single SMS
Sms::sendSms('+254712345678', 'Hello World');

// Send bulk SMS
Sms::sendBulkSms(['+254712345678', '+254712345679'], 'Hello everyone!');

// Send scheduled SMS
Sms::sendScheduledSms('+254712345678', 'Reminder!', '2024-12-25 09:00:00');

// Check delivery status
$status = Sms::getSmsDeliveryStatus('message_id_here');
```

### Using Dependency Injection

```php
use Moffhub\SmsHandler\Services\SmsService;

class NotificationController extends Controller
{
    public function __construct(protected SmsService $smsService) {}

    public function notify(Request $request)
    {
        $this->smsService->sendSms(
            $request->phone,
            $request->message
        );
    }
}
```

### Switching Providers at Runtime

```php
use Moffhub\SmsHandler\SmsManager;

$manager = app(SmsManager::class);

// Use Africa's Talking for this message
$manager->driver('at')->sendSms('+254712345678', 'Via AT');

// Use Twilio for this message
$manager->driver('twilio')->sendSms('+1234567890', 'Via Twilio');
```

## Fallback Provider Chain

Configure a fallback provider that is used automatically when the primary provider fails with a `ProviderException`. Fallback is limited to one level (no chaining beyond the fallback).

```bash
# In .env
ADVANTA_FALLBACK_PROVIDER=africastalking
```

Or in `config/sms.php`:

```php
'providers' => [
    'advanta' => [
        // ...credentials...
        'fallback' => 'africastalking',
    ],
],
```

When the primary provider fails, the package will:
1. Log the failure
2. Automatically retry with the fallback provider
3. Log the fallback activation
4. Return the fallback result (or null if the fallback also fails)

Non-`ProviderException` errors (e.g., validation errors) are not retried and are rethrown.

## Rate Limiting

Configure per-provider rate limits (messages per minute). When the limit is exceeded, messages are automatically queued for later delivery instead of being rejected.

```bash
# In .env - allow 100 messages per minute for Advanta
ADVANTA_RATE_LIMIT=100
```

Or in `config/sms.php`:

```php
'providers' => [
    'advanta' => [
        // ...credentials...
        'rate_limit' => 100, // messages per minute, null = unlimited
    ],
],
```

Programmatic access:

```php
use Moffhub\SmsHandler\Facades\Sms;

// Check remaining attempts
$remaining = Sms::rateLimiter()->remainingAttempts('advanta');

// Clear the rate limiter for a provider
Sms::rateLimiter()->clear('advanta');
```

### Rate Limiting Recommendations

- Set rate limits based on your provider's API quotas to avoid being blocked.
- Start with conservative limits and increase as needed.
- Monitor your queue to ensure rate-limited messages are being delivered.
- Use `null` (unlimited) only for providers with no known API rate limits.

## SMS Templating

Define reusable SMS templates with `{{ variable }}` interpolation:

```php
// config/sms.php
'templates' => [
    'otp' => 'Your verification code is {{ code }}. Valid for {{ minutes }} minutes.',
    'welcome' => 'Welcome {{ name }}! Thanks for joining us.',
    'order_shipped' => ['body' => 'Hi {{ name }}, your order #{{ order_id }} has shipped.'],
],
```

Send a templated SMS:

```php
use Moffhub\SmsHandler\Facades\Sms;

// Fluent API
Sms::template('otp', ['code' => '1234', 'minutes' => '5'])
    ->to('+254712345678')
    ->send();

// Check if a template exists
Sms::templateService()->exists('otp'); // true

// Get all template names
Sms::templateService()->getTemplateNames(); // ['otp', 'welcome', 'order_shipped']
```

Message length is validated after interpolation. If the rendered message exceeds the configured `max_message_length`, an `InvalidMessageException` is thrown.

## Cost Estimation

Estimate the cost of sending an SMS before dispatching. The estimator calculates SMS segment count based on message encoding (GSM-7 vs UCS-2) and multiplies by the configured per-segment cost.

```php
use Moffhub\SmsHandler\Facades\Sms;

$estimate = Sms::estimateCost('Hello world', 100, 'advanta');
// Returns:
// [
//     'segments' => 1,
//     'per_segment_cost' => 1.50,
//     'total_cost' => 150.0,
//     'recipient_count' => 100,
//     'is_unicode' => false,
// ]
```

### Segment Counting Rules

| Encoding | Single SMS | Multi-part (per segment) |
|----------|-----------|--------------------------|
| GSM-7    | 160 chars | 153 chars                |
| UCS-2    | 70 chars  | 67 chars                 |

Unicode characters (emoji, CJK, Arabic, etc.) force UCS-2 encoding, which reduces the per-segment capacity.

Configure per-segment cost in your provider config:

```php
'providers' => [
    'advanta' => [
        // ...credentials...
        'per_segment_cost' => 1.50,
    ],
],
```

When logging to the database (`SMS_LOG_CHANNEL=model`), the `estimated_cost` and `segment_count` columns are automatically populated on each `SmsLog` record.

## Analytics & Success Rate Tracking

Query SMS analytics aggregated from the `sms_logs` table:

```php
use Moffhub\SmsHandler\Facades\Sms;

// Overall summary
$summary = Sms::analytics()->summary();
// ['total_sent' => 1000, 'total_delivered' => 950, 'total_failed' => 50, 'success_rate' => 95.0, ...]

// Filter by provider
$summary = Sms::analytics()->forProvider('twilio')->summary();

// Filter by date range
$summary = Sms::analytics()->last30Days()->summary();
$summary = Sms::analytics()->last7Days()->summary();
$summary = Sms::analytics()->between($from, $to)->summary();

// Combine filters
$summary = Sms::analytics()->forProvider('advanta')->last30Days()->summary();

// Daily breakdown
$breakdown = Sms::analytics()->last30Days()->dailyBreakdown();
// Collection of ['date' => '2024-01-15', 'sent' => 100, 'delivered' => 95, 'failed' => 5]

// Per-provider summary
$providers = Sms::analytics()->perProviderSummary();
// Collection of ['provider' => 'advanta', 'sent' => 500, 'delivered' => 490, 'failed' => 10, 'success_rate' => 98.0]
```

### CLI Stats Command

View SMS statistics from the command line:

```bash
# Last 30 days (default)
php artisan sms:stats

# Last 7 days
php artisan sms:stats --days=7

# Filter by provider
php artisan sms:stats --provider=advanta

# Combined
php artisan sms:stats --provider=twilio --days=14
```

## Error Handling

### Exception Types

The package defines a hierarchy of exceptions:

| Exception | Description |
|-----------|-------------|
| `SmsException` | Base exception class for all SMS errors |
| `ProviderException` | Provider-level failures (API errors, timeouts). Triggers fallback if configured. |
| `InvalidPhoneNumberException` | Invalid, empty, or malformed phone numbers |
| `InvalidMessageException` | Empty or too-long messages |

### Handling Exceptions

```php
use Moffhub\SmsHandler\Exceptions\ProviderException;
use Moffhub\SmsHandler\Exceptions\InvalidPhoneNumberException;
use Moffhub\SmsHandler\Exceptions\InvalidMessageException;

try {
    Sms::sendSms($phone, $message);
} catch (InvalidPhoneNumberException $e) {
    // Phone number validation failed
    // e.g., "Invalid phone number '123': Phone number must have at least 9 digits"
} catch (InvalidMessageException $e) {
    // Message validation failed
    // e.g., "SMS message cannot be empty"
    // e.g., "Message exceeds maximum of 918 characters"
} catch (ProviderException $e) {
    // Provider API error — fallback was already attempted if configured
    // e.g., "SMS provider 'advanta' failed to send: Connection timeout"
}
```

### Events

Listen for SMS lifecycle events:

```php
use Moffhub\SmsHandler\Events\SmsSent;
use Moffhub\SmsHandler\Events\SmsFailed;
use Moffhub\SmsHandler\Events\DeliveryReportReceived;

// In EventServiceProvider
protected $listen = [
    SmsSent::class => [SmsSuccessListener::class],
    SmsFailed::class => [SmsFailureListener::class],
    DeliveryReportReceived::class => [DeliveryReportListener::class],
];

// SmsSent carries: provider, to, message, messageId, response
// SmsFailed carries: provider, to, message, exception
// DeliveryReportReceived carries: provider, messageId, status, phoneNumber, payload
```

## Webhook Setup

Enable webhooks to receive delivery reports from providers:

```bash
SMS_WEBHOOKS_ENABLED=true
SMS_WEBHOOKS_PREFIX=sms/webhooks  # URL prefix
```

This registers POST routes for each provider:

| Provider | Endpoint | Route Name |
|----------|----------|------------|
| Advanta | `POST /sms/webhooks/advanta` | `sms.webhooks.advanta` |
| Africa's Talking | `POST /sms/webhooks/africastalking` | `sms.webhooks.africastalking` |
| Onfon | `POST /sms/webhooks/onfon` | `sms.webhooks.onfon` |
| Nexmo | `POST /sms/webhooks/nexmo` | `sms.webhooks.nexmo` |
| Twilio | `POST /sms/webhooks/twilio` | `sms.webhooks.twilio` |

### Webhook Signature Validation

Set webhook secrets to validate incoming requests:

```bash
SMS_WEBHOOK_SECRET_TWILIO=your_twilio_auth_token
SMS_WEBHOOK_SECRET_AFRICASTALKING=your_callback_token
SMS_WEBHOOK_SECRET_ADVANTA=your_shared_secret
SMS_WEBHOOK_SECRET_NEXMO=your_signature_secret
SMS_WEBHOOK_SECRET_ONFON=your_api_key
```

If a secret is configured, the package validates the signature header on incoming webhook requests. Invalid signatures receive a 403 response.

### Provider Webhook Setup

**Twilio:**
1. Go to your Twilio Console > Phone Numbers > Active Numbers
2. Click your number and set the "A MESSAGE COMES IN" webhook URL to your endpoint
3. Set the Status Callback URL to `https://yourdomain.com/sms/webhooks/twilio`

**Africa's Talking:**
1. Go to your AT Dashboard > SMS > SMS Callback URLs
2. Set the Delivery Reports URL to `https://yourdomain.com/sms/webhooks/africastalking`

**Advanta:**
1. Configure the callback URL in your Advanta dashboard or pass it in the API request
2. Set it to `https://yourdomain.com/sms/webhooks/advanta`

**Nexmo/Vonage:**
1. Go to Vonage Dashboard > Settings
2. Set the SMS Delivery Receipt URL to `https://yourdomain.com/sms/webhooks/nexmo`

**Onfon:**
1. Contact Onfon support to configure your callback URL
2. Set it to `https://yourdomain.com/sms/webhooks/onfon`

### Delivery Report Payload Examples

**Twilio:**
```json
{
    "MessageSid": "SM1234567890",
    "MessageStatus": "delivered",
    "To": "+254712345678",
    "ErrorCode": null
}
```

**Africa's Talking:**
```json
{
    "id": "ATXid_123",
    "status": "Success",
    "phoneNumber": "+254712345678",
    "failureReason": ""
}
```

**Advanta:**
```json
{
    "messageId": "msg123",
    "status": "DeliveredToTerminal",
    "phoneNumber": "254712345678"
}
```

**Nexmo/Vonage:**
```json
{
    "messageId": "0C000000217B7F02",
    "status": "delivered",
    "to": "254712345678",
    "err-code": "0"
}
```

**Onfon:**
```json
{
    "MessageId": "12345",
    "Status": "DELIVERED",
    "Number": "254712345678"
}
```

## Africa's Talking Integration

The library fully supports the [Africa's Talking Bulk SMS API](https://developers.africastalking.com/docs/sms/sending/bulk):

### Sandbox Testing
```bash
AT_USERNAME=sandbox
AT_API_KEY=your_sandbox_api_key
```

### Production
```bash
AT_USERNAME=your_app_username
AT_API_KEY=your_production_api_key
AT_FROM=YOUR_SENDER_ID
```

### Features
- Automatic sandbox/production URL detection
- Phone number formatting (supports 0712..., 254712..., +254712...)
- Bulk SMS with enqueue support
- Sender ID/Short code support
- Detailed response handling with message IDs and costs

## Custom Providers

Create your own provider by extending `CustomProvider`:

```php
use Moffhub\SmsHandler\Providers\CustomProvider;
use Illuminate\Support\Collection;

class MySmsProvider extends CustomProvider
{
    protected function getApiUrl(): string
    {
        return 'https://api.custom.com/send';
    }

    protected function buildPayload(string $to, string $message): array
    {
        return [
            'to' => $to,
            'text' => $message,
            'api_key' => $this->config['key'],
        ];
    }

    protected function handleResponse(mixed $response): ?Collection
    {
        return collect([
            'status' => $response['status'] ?? 'unknown',
        ]);
    }
}
```

Register your provider:

```php
// In a service provider
use Moffhub\SmsHandler\SmsManager;

$this->app->make(SmsManager::class)->extend('custom', function ($app) {
    return new MySmsProvider([
        'key' => config('sms.providers.custom.key'),
    ]);
});
```

Add config:

```php
// config/sms.php
'providers' => [
    'custom' => [
        'key' => env('MY_CUSTOM_API_KEY'),
    ],
],
```

Update `.env`:

```bash
SMS_PROVIDER=custom
MY_CUSTOM_API_KEY=super-secret
```

## Laravel Notifications

Use SMS in Laravel notifications:

```php
use Moffhub\SmsHandler\Notifications\SmsChannel;

class OrderShipped extends Notification
{
    public function via($notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms($notifiable): string
    {
        return 'Your order has been shipped!';
    }
}
```

Ensure your notifiable model has a `routeNotificationForSms` method:

```php
public function routeNotificationForSms(): string
{
    return $this->phone;
}
```

## Logging

SMS messages can be logged to file or database:

```bash
# Log to Laravel's log file
SMS_LOG_CHANNEL=log

# Log to database (requires migration)
SMS_LOG_CHANNEL=model
```

When using database logging, each `SmsLog` record includes:
- `provider` - The provider class used
- `to` - Recipient phone number
- `message` - Message content
- `success` - Boolean success status
- `message_id` - Provider message ID
- `delivery_status` - Updated via webhooks
- `estimated_cost` - Calculated cost based on segments and provider rate
- `segment_count` - Number of SMS segments
- `scheduled_at` - For scheduled messages
- `response` - Raw provider response

### Structured Logging

The package logs structured events to a configurable log channel:

```bash
SMS_STRUCTURED_LOG_CHANNEL=sms  # Optional: dedicated log channel
```

Log events include:
- `sms.sent` - Successful send with provider, recipient, message_id
- `sms.failed` - Failed send with provider, recipient, error details
- `sms.bulk_failed` - Bulk send failure
- `sms.delivery_report` - Incoming delivery report

All log entries are scrubbed of sensitive data (API keys, tokens, secrets).

## Testing & Mocking

### Faking HTTP Requests

Use Laravel's HTTP faking to test SMS sending without making real API calls:

```php
use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Facades\Sms;

Http::fake([
    '*' => Http::response([
        'responses' => [
            [
                'response-code' => 200,
                'response-description' => 'Success',
                'mobile' => '254712345678',
                'messageid' => 'msg123',
            ],
        ],
    ]),
]);

Sms::sendSms('+254712345678', 'Test message');

Http::assertSentCount(1);
```

### Faking Events

Test that events are dispatched correctly:

```php
use Illuminate\Support\Facades\Event;
use Moffhub\SmsHandler\Events\SmsSent;
use Moffhub\SmsHandler\Events\SmsFailed;

Event::fake([SmsSent::class, SmsFailed::class]);

Sms::sendSms('+254712345678', 'Test');

Event::assertDispatched(SmsSent::class, function ($event) {
    return $event->to === '+254712345678';
});
```

### Testing Templates

```php
use Moffhub\SmsHandler\Services\TemplateService;

$service = new TemplateService();
$rendered = $service->render('otp', ['code' => '1234']);
$this->assertEquals('Your code is 1234.', $rendered);
```

### Testing Cost Estimation

```php
use Moffhub\SmsHandler\Facades\Sms;

$estimate = Sms::estimateCost('Short message', 1, 'advanta');
$this->assertEquals(1, $estimate['segments']);
$this->assertEquals(1.50, $estimate['total_cost']);
```

## Troubleshooting

### Common Issues

**SMS not sending:**
1. Check your provider credentials in `.env`
2. Verify `SMS_PROVIDER` is set to a valid provider name
3. Check `SMS_LOG_CHANNEL=log` and review Laravel logs for errors
4. Ensure your queue worker is running if using queued sending

**Validation errors:**
- Phone numbers must have at least 9 digits and no more than 15 (E.164)
- Messages cannot be empty or exceed `SMS_MAX_MESSAGE_LENGTH` (default: 918 characters)
- Phone numbers are auto-formatted; supported formats: `0712345678`, `254712345678`, `+254712345678`

**Rate limiting:**
- If messages are being queued unexpectedly, check `rate_limit` config per provider
- Use `Sms::rateLimiter()->remainingAttempts('provider')` to check remaining quota
- Clear the limiter with `Sms::rateLimiter()->clear('provider')`

**Webhooks not receiving reports:**
- Ensure `SMS_WEBHOOKS_ENABLED=true`
- Verify your webhook URL is publicly accessible (use ngrok for local dev)
- Check webhook secrets match what the provider expects
- Review logs for 403 responses (signature validation failures)

**Fallback not activating:**
- Fallback only triggers on `ProviderException`, not on validation errors
- Verify the fallback provider name matches a configured driver
- Fallback is limited to 1 level -- the fallback provider's own fallback is not used
- Check logs for "SMS fallback activated" messages

**Cost estimation showing 0:**
- Ensure `per_segment_cost` is configured for the provider in `config/sms.php`
- Cost is 0.0 by default if not configured

### Debug Logging

Enable debug-level structured logging:

```php
// config/logging.php
'channels' => [
    'sms' => [
        'driver' => 'daily',
        'path' => storage_path('logs/sms.log'),
        'level' => 'debug',
    ],
],
```

```bash
SMS_STRUCTURED_LOG_CHANNEL=sms
```

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
