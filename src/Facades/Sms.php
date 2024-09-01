<?php

namespace Moffhub\SmsHandler\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void sendSms(string $phoneNumber, string $message)
 * @method static void sendBulkSms(array $phoneNumbers, string $message)
 * @method static string getSmsDeliveryStatus(string $messageId)
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms';
    }
}
