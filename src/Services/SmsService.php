<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
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
    public function getSmsDeliveryStatus($messageId): string
    {
        if ($this->smsManager->driver()->getSmsDeliveryStatus($messageId)) {
            return 'delivered';
        } else {
            $this->logSms(get_class($this->smsManager->driver()), $messageId, 'Delivery status check failed', false);
        }
        return 'not delivered';
    }

    /**
     * @throws Throwable
     */
    public function sendBulkSms(array $recipients, $message): void
    {
        $provider = $this->smsManager->driver();

        if ($provider->sendBulkSms($recipients, $message)) {
            foreach ($recipients as $to) {
                $this->logSms(get_class($provider), $to, $message, true);
            }
        } else {
            foreach ($recipients as $to) {
                $this->logSms(get_class($provider), $to, $message, false);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function sendScheduledSms(string|array $to, string $message, Carbon|CarbonImmutable|string $date)
    {
        switch ($date) {
            case $date instanceof CarbonImmutable:
            case $date instanceof Carbon:
                $date = $date->toDateTimeString();
                break;
            case is_string($date):
                $date = Carbon::parse($date)->toDateTimeString();
                break;
        }
        if (!$date->isFuture()) {
            throw new Exception('Date must be in the future');
        }

        $provider = $this->smsManager->driver();
        $provider->sendScheduledSms($to, $message, $date);

    }

    /**
     * @throws Throwable
     */
    public function sendSms(string $to, string $message): void
    {
        $provider = $this->smsManager->driver();
        $logs = $provider->sendSms($to, $message);
        if ($logs) {
            $this->logSms(get_class($provider), $to, $message, true, $logs);
        } else {
            $this->logSms(get_class($provider), $to, $message, false);
        }
    }

    /**
     * @throws Throwable
     */
    protected function logSms($provider, $to, $message, $success, $response = []): void
    {
        if ($this->logChannel === 'model') {
            $smsLog = new SmsLog;
            $smsLog->provider = $provider;
            $smsLog->to = $to;
            $smsLog->message = $message;
            $smsLog->success = $success;
            $smsLog->response = $response;
            $smsLog->saveOrFail();
        } else {
            logger()->info('SMS sent', [
                'provider' => $provider,
                'to' => $to,
                'message' => $message,
                'success' => $success,
            ]);
        }
    }
}
