<?php

namespace Moffhub\SmsHandler\Providers;

use Moffhub\SmsHandler\Traits\SmsProviderInterface;

abstract class BaseProvider implements SmsProviderInterface
{
    public function sendSms(string $to, string $message): ?object
    {
        return null;
    }

    public function sendScheduledSms(string $to, string $message): ?object
    {
        return null;
    }

    public function sendBulkSms(array $recipients, string $message): ?object
    {
        return null;
    }

    public function sendScheduledBulkSms(array $recipients, string $message): ?object
    {
        return null;
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return '';
    }

    public function getSmsBalance(): int
    {
        return 0;
    }
}
