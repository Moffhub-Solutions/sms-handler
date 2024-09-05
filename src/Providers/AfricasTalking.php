<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

class AfricasTalking extends BaseProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $apiUrl,
    ) {
        //
    }

    public function sendSms(string $to, string $message): ?object
    {
        return null;
    }

    public function sendBulkSms(array $recipients, string $message): ?object
    {
        return null;
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return 'delivered';
    }
}
