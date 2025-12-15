<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Actions\Onfon\SendSmsAction;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;

class OnfonMediaProvider extends BaseProvider
{
    public function __construct(
        protected Application $app,
        protected string $apiKey,
        protected string $apiUrl,
        protected string $senderId,
        protected string $clientId,
    ) {}

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendSms(string $to, string $message, string|null|Carbon $scheduleAt = null): ?Collection
    {
        if ($scheduleAt) {
            return $this->scheduleSmsSend($to, $message, $scheduleAt);
        }

        $phoneNumber = formatPhoneNumber($to, '254');

        try {
            return $this->app->make(SendSmsAction::class)->execute($this->apiUrl, [
                'ApiKey' => $this->apiKey,
                'ClientId' => $this->clientId,
                'SenderId' => $this->senderId,
                'MessageParameters' => [
                    [
                        'Number' => $phoneNumber,
                        'Text' => $message,
                    ],
                ],
                'IsUnicode' => true,
                'IsFlash' => true,
            ], $message);
        } catch (Exception $exception) {
            logger()->error($exception->getMessage(), [
                'to' => $phoneNumber,
                'message' => $message,
            ]);

            return null;
        }
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
                to: formatPhoneNumber($to, '254'),
                message: $message,
                provider: 'onfon',
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
        $allResponses = collect();

        collect($recipients)->chunk(100)->each(function (Collection $chunk) use ($message, &$allResponses) {
            $payload = $chunk->map(fn (string $recipient) => [
                'Number' => formatPhoneNumber($recipient, '254'),
                'Text' => $message,
            ])->values()->toArray();

            try {
                $result = $this->app->make(SendSmsAction::class)->execute($this->apiUrl, [
                    'ApiKey' => $this->apiKey,
                    'ClientId' => $this->clientId,
                    'SenderId' => $this->senderId,
                    'MessageParameters' => $payload,
                    'IsUnicode' => true,
                    'IsFlash' => true,
                ], $message);

                if ($result) {
                    $allResponses = $allResponses->merge($result);
                }
            } catch (Exception $exception) {
                logger()->error($exception->getMessage(), [
                    'recipients' => $chunk->toArray(),
                    'message' => $message,
                ]);
            }
        });

        return $allResponses->isEmpty() ? null : $allResponses;
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
                to: formatPhoneNumber($recipient, '254'),
                message: $message,
                provider: 'onfon',
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
