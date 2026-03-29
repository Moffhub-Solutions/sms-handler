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

class TwilioProvider extends BaseProvider
{
    public function __construct(
        protected string $accountSid,
        protected string $authToken,
        protected string $from,
        protected string $apiUrl = 'https://api.twilio.com',
        protected string $baseUrl = 'https://api.twilio.com',
    ) {}

    public function getAccountSid(): string
    {
        return $this->accountSid;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
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

        $endpoint = "{$this->getBaseUrl()}/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $payload = [
            'To' => $to,
            'From' => $this->from,
            'Body' => $message,
        ];

        $this->logProviderRequest('POST', $endpoint, $payload);

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            logger()->error('Twilio SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        return collect([
            new SmsResponseData(
                messageId: $data['sid'] ?? '',
                status: $data['status'] ?? 'unknown',
                to: $to,
                message: $message,
                provider: 'twilio',
                response: [
                    'sid' => $data['sid'] ?? null,
                    'dateCreated' => $data['date_created'] ?? null,
                    'price' => $data['price'] ?? null,
                ]
            ),
        ]);
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
                provider: 'twilio',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
        ]);
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendScheduledSms(string $to, string $message, Carbon|CarbonImmutable|string $date): ?Collection
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
                provider: 'twilio',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
            $recipients
        ));
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        $endpoint = "{$this->getBaseUrl()}/2010-04-01/Accounts/{$this->accountSid}/Messages/{$messageId}.json";

        $this->logProviderRequest('GET', $endpoint, []);

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)->get($endpoint);

        if (! $response->successful()) {
            return 'unknown';
        }

        return $response->json('status') ?? 'unknown';
    }

    public function getSmsBalance(): int
    {
        $endpoint = "{$this->getBaseUrl()}/2010-04-01/Accounts/{$this->accountSid}/Balance.json";

        $this->logProviderRequest('GET', $endpoint, []);

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)->get($endpoint);

        if (! $response->successful()) {
            logger()->error('Twilio balance check failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 0;
        }

        $balance = $response->json('balance') ?? '0';

        return (int) (float) $balance;
    }
}
