<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Data\SmsResponseData;

class AfricasTalkingProvider extends BaseProvider
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

    public function sendSms(string $to, string $message, string|null|Carbon $scheduleAt = null): ?Collection
    {
        return null;
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendScheduledSms(string $to, string $message, string|Carbon|CarbonImmutable $date): ?Collection
    {
        return $this->sendSms($to, $message, $date);
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
