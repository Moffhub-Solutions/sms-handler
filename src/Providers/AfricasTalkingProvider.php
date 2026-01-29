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
    protected const SANDBOX_API_URL = 'https://api.sandbox.africastalking.com/version1/messaging';

    protected const PRODUCTION_API_URL = 'https://api.africastalking.com/version1/messaging';

    protected const PRODUCTION_BULK_API_URL = 'https://api.africastalking.com/version1/messaging/bulk';

    public function __construct(
        protected string $username,
        protected string $apiKey,
        protected ?string $from = null,
        protected ?string $apiUrl = null,
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

    public function getApiUrl(): string
    {
        if ($this->apiUrl) {
            return $this->apiUrl;
        }

        return $this->username === 'sandbox'
            ? self::SANDBOX_API_URL
            : self::PRODUCTION_API_URL;
    }

    protected function getBulkApiUrl(): string
    {
        return $this->username === 'sandbox'
            ? self::SANDBOX_API_URL
            : self::PRODUCTION_BULK_API_URL;
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

        $response = Http::withHeaders([
            'apiKey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->asForm()->post($this->getApiUrl(), $payload);

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

        $response = Http::withHeaders([
            'apiKey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->asForm()->post($this->getApiUrl(), $payload);

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

    public function getSmsDeliveryStatus(string $messageId): string
    {
        // Africa's Talking doesn't have a direct API to fetch delivery status by messageId.
        // They use delivery report callbacks instead. Return the messageId status from
        // your callback handler/database if you've set up delivery reports.
        // See: https://africastalking.com/docs/sms/callback
        return 'pending';
    }

    public function getSmsBalance(): int
    {
        $apiUrl = $this->username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/user'
            : 'https://api.africastalking.com/version1/user';

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
