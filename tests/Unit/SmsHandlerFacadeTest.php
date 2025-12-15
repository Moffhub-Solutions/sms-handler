<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Facades\Sms;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsHandlerFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '254712345678',
                        'messageid' => 'msg123',
                    ],
                ],
            ]),
        ]);
    }

    public function test_sends_sms_via_facade(): void
    {
        Sms::sendSms('0712345678', 'Test message');

        Http::assertSentCount(1);
    }

    public function test_sends_bulk_sms_via_facade(): void
    {
        Sms::sendBulkSms(['0712345678', '0712345679'], 'Bulk test message');

        Http::assertSentCount(1);
    }

    public function test_gets_delivery_status_via_facade(): void
    {
        $status = Sms::getSmsDeliveryStatus('msg123');

        $this->assertIsString($status);
    }

    public function test_scheduled_sms_throws_for_past_date(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Date must be in the future');

        Sms::sendScheduledSms('0712345678', 'Test', '2020-01-01 12:00:00');
    }

    public function test_scheduled_sms_dispatches_job_via_facade(): void
    {
        Queue::fake();

        $futureDate = Carbon::now()->addHour();
        Sms::sendScheduledSms('0712345678', 'Scheduled message', $futureDate);

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_scheduled_bulk_sms_dispatches_job_via_facade(): void
    {
        Queue::fake();

        $futureDate = Carbon::now()->addHour();
        Sms::sendScheduledBulkSms(['0712345678', '0712345679'], 'Scheduled bulk', $futureDate);

        Queue::assertPushed(SendBulkSmsJob::class);
    }
}
