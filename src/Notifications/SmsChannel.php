<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Notifications;

use Exception;
use Illuminate\Support\Facades\Notification;
use Moffhub\SmsHandler\Services\SmsService;

class SmsChannel
{
    public function __construct(protected SmsService $smsService)
    {
        //
    }

    /**
     * @throws Exception
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            throw new Exception('Notification is missing toSms method.');
        }

        $message = $notification->toSms($notifiable);

        $to = $notifiable->routeNotificationFor('sms', $notification);
        $this->smsService->sendSms($to, $message);
    }
}
