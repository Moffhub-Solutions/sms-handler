<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Notifications;

use Exception;
use Illuminate\Notifications\Notification;
use Moffhub\SmsHandler\Services\SmsService;
use Throwable;

class SmsChannel
{
    public function __construct(protected SmsService $smsService) {}

    /**
     * @throws Exception|Throwable
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            throw new Exception('Notification is missing toSms method.');
        }

        $message = $notification->toSms($notifiable);
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (!$to) {
            return;
        }

        $this->smsService->sendSms($to, $message);
    }
}
