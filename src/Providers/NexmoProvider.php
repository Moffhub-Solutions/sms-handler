<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;

class NexmoProvider extends BaseProvider
{
    public function __construct(
        protected string $key,
        protected string $secret,
        protected string $from = 'NEXMO',
        protected string $apiUrl = 'https://rest.nexmo.com/sms/json',
        protected string $baseUrl = 'https://rest.nexmo.com',
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendSms(string $to, string $message, string|null|Carbon $scheduleAt = null): ?Collection
    {
        if ($scheduleAt) {
            return $this->scheduleSmsSend($to, $message, $scheduleAt);
        }

        $payload = [
            'api_key' => $this->key,
            'api_secret' => $this->secret,
            'to' => $to,
            'from' => $this->from,
            'text' => $message,
        ];

        $this->logProviderRequest('POST', $this->apiUrl, $payload);

        $response = Http::post($this->apiUrl, $payload);

        if (! $response->successful()) {
            logger()->error('Nexmo SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $body = $response->json();
        $messages = $body['messages'] ?? [];

        if (empty($messages)) {
            return null;
        }

        return collect($messages)->map(fn (array $msg) => new SmsResponseData(
            messageId: $msg['message-id'] ?? '',
            status: $msg['status'] === '0' ? 'sent' : 'failed',
            to: $msg['to'] ?? $to,
            message: $message,
            provider: 'nexmo',
            response: [
                'status' => $msg['status'] ?? null,
                'remainingBalance' => $msg['remaining-balance'] ?? null,
                'messagePrice' => $msg['message-price'] ?? null,
                'network' => $msg['network'] ?? null,
            ]
        ));
    }

    /**
     * @return Collection<int, SmsResponseData>
     */
    protected function scheduleSmsSend(string $to, string $message, Carbon|string $scheduleAt): Collection
    {
        $scheduledTime = $scheduleAt instanceof Carbon ? $scheduleAt : Carbon::parse($scheduleAt);

        SendSmsJob::dispatch($to, $message)->delay($scheduledTime);

        return collect([
            new SmsResponseData(
                messageId: '',
                status: 'scheduled',
                to: $to,
                message: $message,
                provider: 'nexmo',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
        ]);
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendScheduledSms(string $to, string $message, string|Carbon|CarbonImmutable $date): ?Collection
    {
        $scheduledTime = match (true) {
            $date instanceof CarbonImmutable => $date->toMutable(),
            $date instanceof Carbon => $date,
            default => Carbon::parse($date),
        };

        return $this->sendSms($to, $message, $scheduledTime);
    }

    // sendBulkSms is inherited from BaseProvider (loops sendSms per recipient)

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?Collection
    {
        $scheduledTime = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        SendBulkSmsJob::dispatch($recipients, $message)->delay($scheduledTime);

        return collect(array_map(
            fn (string $recipient) => new SmsResponseData(
                messageId: '',
                status: 'scheduled',
                to: $recipient,
                message: $message,
                provider: 'nexmo',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
            $recipients
        ));
    }

    /**
     * Poll Nexmo/Vonage for delivery status using the search messages API.
     *
     * @see https://developer.vonage.com/api/sms#search-message
     */
    public function getSmsDeliveryStatus(string $messageId): string
    {
        $url = $this->getBaseUrl().'/search/message';

        $this->logProviderRequest('GET', $url, ['id' => $messageId]);

        $response = Http::get($url, [
            'api_key' => $this->key,
            'api_secret' => $this->secret,
            'id' => $messageId,
        ]);

        if (! $response->successful()) {
            return 'unknown';
        }

        $data = $response->json();

        // Nexmo returns status in the response
        $status = $data['status'] ?? null;

        if ($status === null) {
            return 'unknown';
        }

        // Map Nexmo status codes to human-readable statuses
        return match ($status) {
            'DELIVERED', 'delivered' => 'delivered',
            'EXPIRED', 'expired' => 'expired',
            'FAILED', 'failed' => 'failed',
            'REJECTED', 'rejected' => 'rejected',
            'ACCEPTED', 'accepted' => 'sent',
            'BUFFERED', 'buffered' => 'pending',
            default => $status,
        };
    }

    public function getSmsBalance(): int
    {
        $url = $this->getBaseUrl().'/account/get-balance';

        $this->logProviderRequest('GET', $url, []);

        $response = Http::get($url, [
            'api_key' => $this->key,
            'api_secret' => $this->secret,
        ]);

        if (! $response->successful()) {
            logger()->error('Nexmo balance check failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 0;
        }

        $balance = $response->json('value') ?? 0;

        return (int) (float) $balance;
    }
}
