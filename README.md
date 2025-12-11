## SMS Handler

[![Latest Version on Packagist](https://img.shields.io/packagist/v/moffhub/sms-lib.svg?style=flat-square)](https://packagist.org/packages/moffhub/sms-lib)
[![Total Downloads](https://img.shields.io/packagist/dt/moffhub/sms-lib.svg?style=flat-square)](https://packagist.org/packages/moffhub/sms-lib)
    
This library is used to send interface with the SMS API. It is used to send SMS messages to users.

#### Features

- [x] Send SMS
- [ ] Send Scheduled SMS
- [ ] Send Bulk SMS
- [ ] Send Bulk Scheduled SMS
- [ ] Get Message Info
- [x] Log SMS messages in the database
- [ ] Get SMS messages from the database

#### Providers

- [x] Advanta SMS Provider
- [x] Africa's Talking SMS Provider
- [x] Twilio SMS Provider
- [x] Nexmo SMS Provider
- [x] Custom SMS Provider


## Installation

You can install the package via composer:

```bash
# Provider selection
SMS_PROVIDER=advanta  # or at, onfon, twilio, nexmo

# Advanta
ADVANTA_API_KEY=
ADVANTA_API_URL=
ADVANTA_BULK_API_URL=
ADVANTA_PARTNER_ID=
ADVANTA_SHORT_CODE=

# Africa's Talking
AT_API_KEY=
AT_API_URL=

# Onfon Media
ONFON_API_KEY=
ONFON_API_URL=
ONFON_SENDER_ID=
ONFON_CLIENT_ID=

# Nexmo
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
SMS_LOG_CHANNEL=log
````

```bash
composer require moffhub/sms-handler
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Moffhub\SmsHandler\SmsHandlerServiceProvider" --tag=config
php artisan vendor:publish --tag=migrations
php artisan migrate
```

### Available Methods
The library provides simple methods you can use

```sendSms($to, $message)```
This method sends a single SMS to a single recipient


``sendBulkSms($to, $message)``
This method sends a single SMS to multiple recipients

``sendScheduledSms($to, $message, $time)``
This method sends a single SMS to a single recipient at a scheduled time

``sendBulkScheduledSms($to, $message, $time)``
This method sends a single SMS to multiple recipients at a scheduled time


``getMessageInfo($messageId)``
This method gets the status of a message


The package also logs the messages and their responses in the database. You can view the messages in the database by running the command below


### Usage

```php
use Moffhub\SmsHandler\SmsHandler;

$sms = new SmsHandler();

$sms->sendSms('0700000000', 'Hello World');

$sms->sendScheduledSms('0700000000', 'Hello World', '2024-12-12 12:00');
```
or 

```php
use Moffhub\SmsHandler\Facades\Sms;

SendSms::sendSms('0700000000', 'Hello World');

SendSms::sendScheduledSms('0700000000', 'Hello World', '2024-12-12 12:00');
```
### Custom Providers
```php
class MySmsProvider extends CustomProvider
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    protected function getApiUrl(): string
    {
        return 'https://api.custom.com/send';
    }

    protected function buildPayload(string $to, string $message): array
    {
        return [
            'to' => $to,
            'text' => $message,
            'api_key' => $this->config['key'] ?? throw new \InvalidArgumentException('Missing API key'),
        ];
    }

    protected function handleResponse(mixed $response): ?Collection
    {
        return collect([
            'status' => $response['status'] ?? 'unknown',
        ]);
    }

    protected function afterSend($response, $to, $message): void
    {
        // Custom logic like saving to DB or event dispatching
    }
}
```

Add your custom provider to the config file under `providers` array:

```php
'providers' => [
    'custom' => [
        'key' => env('MY_CUSTOM_API_KEY'),
    ],
],
```

extend the sms manager to use your custom provider:

```php
use Moffhub\SmsHandler\SmsManager;
use App\Sms\MySmsProvider;

$this->app->make(SmsManager::class)->extend('custom', function ($app) {
    return new MySmsProvider([
        'key' => config('sms.providers.custom.key'),
    ]);
});
``` 

update .env file to use your custom provider:

```bash
SMS_PROVIDER=custom
MY_CUSTOM_API_KEY=super-secret
```

