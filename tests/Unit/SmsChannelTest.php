<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Notifications\SmsChannel;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsChannelTest extends TestCase
{
    public function test_sends_sms_via_channel(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '254712345678',
                        'messageid' => 'msg_channel_test',
                        'networkid' => 'net_1',
                    ],
                ],
            ]),
        ]);

        $channel = $this->app->make(SmsChannel::class);
        $notifiable = new TestNotifiable;
        $notification = new TestSmsNotification('Hello from channel');

        $channel->send($notifiable, $notification);

        Http::assertSentCount(1);
    }

    public function test_does_not_send_when_no_phone_number(): void
    {
        Http::fake();

        $channel = $this->app->make(SmsChannel::class);
        $notifiable = new TestNotifiableWithoutPhone;
        $notification = new TestSmsNotification('Hello');

        $channel->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_throws_when_notification_missing_to_sms_method(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Notification is missing toSms method.');

        $channel = $this->app->make(SmsChannel::class);
        $notifiable = new TestNotifiable;
        $notification = new TestNotificationWithoutToSms;

        $channel->send($notifiable, $notification);
    }

    public function test_channel_uses_sms_service(): void
    {
        $service = $this->createMock(SmsService::class);
        $service->expects($this->once())
            ->method('sendSms')
            ->with('+254712345678', 'Test message');

        $channel = new SmsChannel($service);
        $notifiable = new TestNotifiable;
        $notification = new TestSmsNotification('Test message');

        $channel->send($notifiable, $notification);
    }
}

class TestNotifiable
{
    public function routeNotificationFor(string $channel, $notification = null): ?string
    {
        return '+254712345678';
    }
}

class TestNotifiableWithoutPhone
{
    public function routeNotificationFor(string $channel, $notification = null): ?string
    {
        return null;
    }
}

class TestSmsNotification extends Notification
{
    public function __construct(protected string $text) {}

    public function toSms($notifiable): string
    {
        return $this->text;
    }
}

class TestNotificationWithoutToSms extends Notification {}
