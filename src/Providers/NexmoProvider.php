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

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendSms(string $to, string $message, string|null|Carbon $scheduleAt = null): ?Collection
    {
        if ($scheduleAt) {
            return $this->scheduleSmsSend($to, $message, $scheduleAt);
        }

        $response = Http::post($this->apiUrl, [
            'api_key' => $this->key,
            'api_secret' => $this->secret,
            'to' => $to,
            'from' => $this->from,
            'text' => $message,
        ]);

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

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        $responses = collect();

        foreach ($recipients as $recipient) {
            $result = $this->sendSms($recipient, $message);
            if ($result) {
                $responses = $responses->merge($result);
            }
        }

        return $responses->isEmpty() ? null : $responses;
    }

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

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return 'pending';
    }
}
