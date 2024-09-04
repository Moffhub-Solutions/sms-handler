<?php

namespace Moffhub\SmsHandler\Traits;

interface SmsProviderInterface
{
    public function sendSms(string $to, string $message): ?object;

    public function sendScheduledSms(string $to, string $message): ?object;

    public function sendBulkSms(array $recipients, string $message): ?object;

    public function sendScheduledBulkSms(array $recipients, string $message): ?object;

    public function getSmsDeliveryStatus(string $messageId): string;

    public function getSmsBalance(): int;
}
