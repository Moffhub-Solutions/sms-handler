<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Traits;

use Carbon\CarbonImmutable;

interface SmsProviderInterface
{
    public function sendSms(string $to, string $message): ?object;

    public function sendScheduledSms(string $to, string $message, CarbonImmutable|string $date): ?object;

    public function sendBulkSms(array $recipients, string $message): ?object;

    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?object;

    public function getSmsDeliveryStatus(string $messageId): string;

    public function getSmsBalance(): int;
}
