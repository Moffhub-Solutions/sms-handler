<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Contracts;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface SmsProviderInterface
{
    public function getSmsBalance(): int;

    public function getSmsDeliveryStatus(string $messageId): string;

    public function sendBulkSms(array $recipients, string $message): ?Collection;

    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?Collection;

    public function sendScheduledSms(string $to, string $message, Carbon|CarbonImmutable|string $date): ?Collection;

    public function sendSms(string $to, string $message, Carbon|string|null $scheduleAt = null): ?Collection;
}
