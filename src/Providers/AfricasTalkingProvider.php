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

class AfricasTalkingProvider extends BaseProvider
{
    protected const SANDBOX_BASE_URL = 'https://api.sandbox.africastalking.com';

    protected const PRODUCTION_BASE_URL = 'https://api.africastalking.com';

    public function __construct(
        protected string $username,
        protected string $apiKey,
        protected ?string $from = null,
        protected ?string $apiUrl = null,
        protected ?string $baseUrl = null,
    ) {}

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getBaseUrl(): string
    {
        if ($this->baseUrl) {
            return rtrim($this->baseUrl, '/');
        }

        return $this->username === 'sandbox'
            ? self::SANDBOX_BASE_URL
            : self::PRODUCTION_BASE_URL;
    }

    public function getApiUrl(): string
    {
        if ($this->apiUrl) {
            return $this->apiUrl;
        }

        return $this->getBaseUrl().'/version1/messaging';
    }

    protected function getBulkApiUrl(): string
    {
        if ($this->username === 'sandbox') {
            return $this->getBaseUrl().'/version1/messaging';
        }

        return $this->getBaseUrl().'/version1/messaging/bulk';
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendSms(string $to, string $message, Carbon|string|null $scheduleAt = null): ?Collection
    {
        if ($scheduleAt) {
            return $this->scheduleSmsSend($to, $message, $scheduleAt);
        }

        return $this->executeSend($to, $message);
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    protected function executeSend(string $to, string $message): ?Collection
    {
        $formattedPhone = $this->formatPhoneNumber($to);

        $payload = [
            'username' => $this->username,
            'to' => $formattedPhone,
            'message' => $message,
        ];

        if ($this->from) {
            $payload['from'] = $this->from;
        }

        $url = $this->getApiUrl();

        $this->logProviderRequest('POST', $url, $payload);

        $response = Http::withHeaders([
            'apiKey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->asForm()->post($url, $payload);

        if (! $response->successful()) {
            logger()->error('Africa\'s Talking SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $this->parseResponse($response->json(), $message);
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
                to: $this->formatPhoneNumber($to),
                message: $message,
                provider: 'africastalking',
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

    /**
     * Africa's Talking supports native bulk SMS via their API (comma-separated recipients).
     * Override the BaseProvider loop implementation.
     *
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        $formattedRecipients = array_map(
            fn (string $phone) => $this->formatPhoneNumber($phone),
            $recipients
        );

        $payload = [
            'username' => $this->username,
            'to' => implode(',', $formattedRecipients),
            'message' => $message,
            'enqueue' => 1,
        ];

        if ($this->from) {
            $payload['from'] = $this->from;
        }

        $url = $this->getApiUrl();

        $this->logProviderRequest('POST', $url, $payload);

        $response = Http::withHeaders([
            'apiKey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->asForm()->post($url, $payload);

        if (! $response->successful()) {
            logger()->error('Africa\'s Talking Bulk SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $this->parseResponse($response->json(), $message);
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
                to: $this->formatPhoneNumber($recipient),
                message: $message,
                provider: 'africastalking',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
            $recipients
        ));
    }

    /**
     * Poll Africa's Talking for delivery status using the fetch messages API.
     *
     * Note: Africa's Talking primarily uses delivery report callbacks.
     * This method uses their messaging fetch endpoint as a fallback to check status.
     *
     * @see https://africastalking.com/docs/sms/fetching
     */
    public function getSmsDeliveryStatus(string $messageId): string
    {
        $url = $this->getBaseUrl().'/version1/messaging';

        $this->logProviderRequest('GET', $url, ['username' => $this->username]);

        $response = Http::withHeaders([
            'apiKey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->get($url, [
            'username' => $this->username,
            'lastReceivedId' => 0,
        ]);

        if (! $response->successful()) {
            return 'unknown';
        }

        // Search through fetched messages for our message ID
        $data = $response->json();
        $messages = $data['SMSMessageData']['Messages'] ?? [];

        foreach ($messages as $msg) {
            if (($msg['id'] ?? '') === $messageId || ($msg['messageId'] ?? '') === $messageId) {
                return $msg['status'] ?? 'unknown';
            }
        }

        // If not found in fetched messages, the status is not available via polling
        return 'pending';
    }

    public function getSmsBalance(): int
    {
        $apiUrl = $this->getBaseUrl().'/version1/user';

        $this->logProviderRequest('GET', $apiUrl, ['username' => $this->username]);

        $response = Http::withHeaders([
            'apiKey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->get($apiUrl, [
            'username' => $this->username,
        ]);

        if (! $response->successful()) {
            logger()->error('Africa\'s Talking balance check failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 0;
        }

        $data = $response->json();
        $balance = $data['UserData']['balance'] ?? '0';

        // Balance comes as string like "KES 100.00", extract numeric part
        preg_match('/[\d.]+/', $balance, $matches);

        return (int) ($matches[0] ?? 0);
    }

    /**
     * @return Collection<int, SmsResponseData>
     */
    protected function parseResponse(array $responseData, string $message): Collection
    {
        $data = $responseData['SMSMessageData'] ?? [];
        $recipients = $data['Recipients'] ?? [];

        return collect($recipients)->map(fn (array $recipient) => new SmsResponseData(
            messageId: $recipient['messageId'] ?? '',
            status: $recipient['status'] ?? 'unknown',
            to: $recipient['number'] ?? '',
            message: $message,
            provider: 'africastalking',
            response: [
                'statusCode' => $recipient['statusCode'] ?? null,
                'cost' => $recipient['cost'] ?? null,
            ]
        ));
    }

    protected function formatPhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '0')) {
            return '+254'.substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '254')) {
            return '+'.$cleaned;
        }

        return '+254'.$cleaned;
    }
}
