<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use BadMethodCallException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Traits\SmsProviderInterface;

abstract class BaseProvider implements SmsProviderInterface
{
    public function sendSms(string $to, string $message): ?Collection
    {
        throw new BadMethodCallException(static::class.' must implement sendSms.');
    }

    public function sendScheduledSms(string $to, string $message, Carbon|CarbonImmutable|string $date): ?Collection
    {
        throw new BadMethodCallException(static::class.' must implement sendScheduledSms.');
    }

    public function sendBulkSms(array $recipients, string $message): ?object
    {
        throw new BadMethodCallException(static::class.' must implement sendBulkSms.');
    }

    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?object
    {
        throw new BadMethodCallException(static::class.' must implement sendScheduledBulkSms.');
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
