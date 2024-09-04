<?php

namespace Moffhub\SmsHandler\Services;

use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\SmsManager;

class SmsService
{
    protected string $logChannel;

    public function __construct(protected SmsManager $smsManager)
    {
        $this->logChannel = config('sms.log_channel', 'log');
    }

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

    protected function logSms($provider, $to, $message, $success, $response = []): void
    {
        if ($this->logChannel === 'model') {
            SmsLog::query()->firstOrCreate([
                'provider' => $provider,
                'to' => $to,
                'message' => $message,
                'success' => $success,
                'response' => $response,
            ]);
        } else {
            logger()->info('SMS sent', [
                'provider' => $provider,
                'to' => $to,
                'message' => $message,
                'success' => $success,
            ]);
        }
    }

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

    public function getSmsDeliveryStatus($messageId)
    {
        if ($this->smsManager->driver()->getSmsDeliveryStatus($messageId)) {
            return 'delivered';
        } else {
            $this->logSms(get_class($this->smsManager->driver()), $messageId, 'Delivery status check failed', false);
        }
    }
}
