<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Illuminate\Support\Collection;

class AfricasTalking extends BaseProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $apiUrl,
    ) {
        //
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function sendSms(string $to, string $message): ?Collection
    {
        return null;
    }

    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        return null;
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return 'delivered';
    }
}
