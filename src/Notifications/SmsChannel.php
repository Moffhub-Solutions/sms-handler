<?php

namespace Moffhub\SmsHandler\Notifications;

use Exception;
use Illuminate\Support\Facades\Notification;
use Moffhub\SmsHandler\Services\SmsService;

class SmsChannel
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
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
