<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Traits;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface SmsProviderInterface
{
    public function getSmsBalance(): int;

    public function getSmsDeliveryStatus(string $messageId): string;

    public function sendBulkSms(array $recipients, string $message): ?object;

    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?object;

    public function sendScheduledSms(string $to, string $message, CarbonImmutable|string $date): ?Collection;

    public function sendSms(string $to, string $message, Carbon|string|null $scheduleAt = null): ?Collection;
}
