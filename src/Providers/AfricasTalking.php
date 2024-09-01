<?php

namespace Moffhub\SmsHandler\Providers;

use Moffhub\SmsHandler\Traits\SmsProviderInterface;

class AfricasTalking implements SmsProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $apiUrl,
    ) {
        //
    }

    public function sendSms(string $to, string $message): bool
    {
        return false;
    }

    public function sendBulkSms(array $recipients, string $message): bool
    {
        return false;
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return 'delivered';
    }
}
