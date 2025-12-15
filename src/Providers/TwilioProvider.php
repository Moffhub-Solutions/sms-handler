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

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendSms(string $to, string $message, string|null|Carbon $scheduleAt = null): ?Collection
    {
        if ($scheduleAt) {
            return $this->scheduleSmsSend($to, $message, $scheduleAt);
        }

        $endpoint = "{$this->apiUrl}/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post($endpoint, [
                'To' => $to,
                'From' => $this->from,
                'Body' => $message,
            ]);

        if (!$response->successful()) {
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
            )
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
            )
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
            fn(string $recipient) => new SmsResponseData(
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
        $endpoint = "{$this->apiUrl}/2010-04-01/Accounts/{$this->accountSid}/Messages/{$messageId}.json";

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)->get($endpoint);

        if (!$response->successful()) {
            return 'unknown';
        }

        return $response->json('status') ?? 'unknown';
    }
}
