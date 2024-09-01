<?php
namespace Moffhub\SmsHandler\Traits;

interface SmsProviderInterface
{
    public function sendSms(string $to, string $message): bool;

    public function sendBulkSms(array $recipients, string $message): bool;

    public function getSmsDeliveryStatus(string $messageId): string;
}
