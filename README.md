## SMS Lib

This library is used to send interface with the SMS API. It is used to send SMS messages to users.

[ ] Add Advanta sms provider
[ ] Add Africastalking sms provider
[ ] Add Twilio sms provider
[ ] Add Nexmo sms provider
[ ] Add Custom sms provider

## Installation

You can install the package via composer:

```bash
SMS_PROVIDER=advanta

ADVANTA_API_KEY=
ADVANTA_API_URL=
ADVANTA_API_URL=
ADVANTA_PARTNER_ID=
ADVANTA_SHORT_CODE=

AT_API_KEY=
AT_API_URL=

SMS_LOG_CHANNEL=
````

```bash
composer require moffhub/sms-lib
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Moffhub\SmsLib\SmsLibServiceProvider" --tag="config"
```

### Configuration
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



