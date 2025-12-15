## SMS Handler

[![Latest Version on Packagist](https://img.shields.io/packagist/v/moffhub/sms-handler.svg?style=flat-square)](https://packagist.org/packages/moffhub/sms-handler)
[![Total Downloads](https://img.shields.io/packagist/dt/moffhub/sms-handler.svg?style=flat-square)](https://packagist.org/packages/moffhub/sms-handler)
    
A simple, unified SMS integration library for Laravel. Send SMS messages through multiple providers with a consistent API.

## Features

- [x] Send SMS
- [x] Send Scheduled SMS
- [x] Send Bulk SMS
- [x] Log SMS messages (database or file)
- [x] Multiple provider support
- [x] Custom provider extensibility

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
php artisan vendor:publish --provider="Moffhub\SmsHandler\SmsHandlerServiceProvider" --tag=config
php artisan vendor:publish --tag=migrations
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

# Advanta
ADVANTA_API_KEY=
ADVANTA_API_URL=
ADVANTA_BULK_API_URL=
ADVANTA_PARTNER_ID=
ADVANTA_SHORT_CODE=

# Onfon Media
ONFON_API_KEY=
ONFON_API_URL=
ONFON_SENDER_ID=
ONFON_CLIENT_ID=

# Nexmo/Vonage
NEXMO_KEY=
NEXMO_SECRET=
NEXMO_FROM=NEXMO
NEXMO_API_URL=https://rest.nexmo.com/sms/json

# Twilio
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
TWILIO_API_URL=https://api.twilio.com

# Logging
SMS_LOG_CHANNEL=log  # Options: log, model
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

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
