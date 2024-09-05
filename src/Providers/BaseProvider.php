<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Traits\SmsProviderInterface;

abstract class BaseProvider implements SmsProviderInterface
{
    public function sendSms(string $to, string $message): ?Collection
    {
        return null;
    }

    public function sendScheduledSms(string $to, string $message, CarbonImmutable|string $date): ?Collection
    {
        return null;
    }

    public function sendBulkSms(array $recipients, string $message): ?object
    {
        return null;
    }

    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?object
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
