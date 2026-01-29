<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\SmsManager;
use Throwable;

class SmsService
{
    protected string $logChannel;

    public function __construct(protected SmsManager $smsManager)
    {
        $this->logChannel = config('sms.log_channel', 'log');
    }

    /**
     * @throws Throwable
     */
    public function getSmsDeliveryStatus(string $messageId): string
    {
        return $this->smsManager->driver()->getSmsDeliveryStatus($messageId);
    }

    /**
     * @throws Throwable
     */
    public function getSmsBalance(): int
    {
        return $this->smsManager->driver()->getSmsBalance();
    }

    /**
     * Get the list of available SMS providers.
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return $this->smsManager->getAvailableProviders();
    }

    /**
     * Check if a provider is configured.
     */
    public function isProviderConfigured(string $provider): bool
    {
        return $this->smsManager->isProviderConfigured($provider);
    }

    /**
     * Get the current default provider name.
     */
    public function getDefaultProvider(): string
    {
        return $this->smsManager->getDefaultDriver();
    }

    /**
     * @throws Throwable
     */
    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        $provider = $this->smsManager->driver();
        $responses = $provider->sendBulkSms($recipients, $message);

        if ($responses) {
            $responses->each(function (SmsResponseData $response) use ($message) {
                $this->logSms(
                    get_class($this->smsManager->driver()),
                    $response->to,
                    $message,
                    $this->isSuccessfulStatus($response->status),
                    $response
                );
            });
        } else {
            foreach ($recipients as $recipient) {
                $this->logSms(get_class($provider), $recipient, $message, false);
            }
        }

        return $responses;
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function sendScheduledSms(string|array $to, string $message, Carbon|CarbonImmutable|string $date): ?Collection
    {
        $scheduledDate = match (true) {
            $date instanceof CarbonImmutable => $date->toMutable(),
            $date instanceof Carbon => $date,
            default => Carbon::parse($date),
        };

        if (! $scheduledDate->isFuture()) {
            throw new Exception('Date must be in the future');
        }

        $provider = $this->smsManager->driver();

        if (is_array($to)) {
            return $provider->sendScheduledBulkSms($to, $message, CarbonImmutable::parse($scheduledDate));
        }

        return $provider->sendScheduledSms($to, $message, $scheduledDate);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function sendScheduledBulkSms(array $recipients, string $message, Carbon|CarbonImmutable|string $date): ?Collection
    {
        $scheduledDate = match (true) {
            $date instanceof CarbonImmutable => $date,
            $date instanceof Carbon => CarbonImmutable::parse($date),
            default => CarbonImmutable::parse($date),
        };

        if (! $scheduledDate->isFuture()) {
            throw new Exception('Date must be in the future');
        }

        $provider = $this->smsManager->driver();

        return $provider->sendScheduledBulkSms($recipients, $message, $scheduledDate);
    }

    /**
     * @throws Throwable
     */
    public function sendSms(string $to, string $message): ?Collection
    {
        $provider = $this->smsManager->driver();
        $logs = $provider->sendSms($to, $message);

        if ($logs && $logs->isNotEmpty()) {
            $logs->each(function (SmsResponseData $response) use ($to, $message) {
                $this->logSms(
                    get_class($this->smsManager->driver()),
                    $to,
                    $message,
                    $this->isSuccessfulStatus($response->status),
                    $response
                );
            });
        } else {
            $this->logSms(get_class($provider), $to, $message, false);
        }

        return $logs;
    }

    protected function isSuccessfulStatus(string $status): bool
    {
        $successStatuses = [
            // AT statuses
            'Success',
            'Sent',
            'Queued',
            'Processed',
            'scheduled',
            // Advanta response codes
            '200',
            '1701',
            // Twilio statuses
            'queued',
            'sent',
            'delivered',
            // Nexmo statuses
            '0',
        ];

        return in_array($status, $successStatuses, true);
    }

    /**
     * @throws Throwable
     */
    protected function logSms(string $provider, string $to, string $message, bool $success, mixed $response = null): void
    {
        if ($this->logChannel === 'model') {
            $smsLog = new SmsLog;
            $smsLog->provider = $provider;
            $smsLog->to = $to;
            $smsLog->message = $message;
            $smsLog->success = $success;

            if ($response instanceof SmsResponseData) {
                $smsLog->message_id = $response->messageId ?: null;
                $smsLog->response = $response->response;
            } else {
                $smsLog->response = $response;
            }

            $smsLog->saveOrFail();
        } else {
            logger()->info('SMS sent', [
                'provider' => $provider,
                'to' => $to,
                'message' => $message,
                'success' => $success,
                'message_id' => $response instanceof SmsResponseData ? $response->messageId : null,
            ]);
        }
    }
}
