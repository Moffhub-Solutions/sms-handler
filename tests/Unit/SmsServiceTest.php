<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;
use Throwable;

class SmsServiceTest extends TestCase
{
    protected SmsManager $smsManager;

    protected SmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsManager = $this->app->make(SmsManager::class);
        $this->smsService = new SmsService($this->smsManager);
    }

    /**
     * @throws Throwable
     */
    public function test_sends_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '254712345678',
                        'messageid' => 'msg123',
                        'networkid' => 'net456',
                    ],
                ],
            ]),
        ]);

        $this->smsService->sendSms('0712345678', 'Test message');

        Http::assertSentCount(1);
    }

    /**
     * @throws Throwable
     */
    public function test_handles_failed_sms_gracefully(): void
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $this->smsService->sendSms('0712345678', 'Test message');

        Http::assertSentCount(1);
    }

    /**
     * @throws Throwable
     */
    public function test_sends_bulk_sms(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    ['response-code' => 200, 'mobile' => '254712345678', 'messageid' => 'msg1'],
                    ['response-code' => 200, 'mobile' => '254712345679', 'messageid' => 'msg2'],
                ],
            ]),
        ]);

        $this->smsService->sendBulkSms(['0712345678', '0712345679'], 'Bulk test message');

        Http::assertSentCount(1);
    }

    public function test_scheduled_sms_throws_exception_for_past_date(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Date must be in the future');

        $this->smsService->sendScheduledSms('0712345678', 'Test', '2020-01-01 12:00:00');
    }

    public function test_gets_delivery_status(): void
    {
        $status = $this->smsService->getSmsDeliveryStatus('msg123');

        $this->assertIsString($status);
    }

    public function test_scheduled_sms_dispatches_job_for_future_date(): void
    {
        Queue::fake();

        $futureDate = Carbon::now()->addDay();
        $this->smsService->sendScheduledSms('0712345678', 'Scheduled message', $futureDate);

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_scheduled_bulk_sms_dispatches_job_for_future_date(): void
    {
        Queue::fake();

        $futureDate = Carbon::now()->addDay();
        $this->smsService->sendScheduledBulkSms(
            ['0712345678', '0712345679'],
            'Scheduled bulk message',
            $futureDate
        );

        Queue::assertPushed(SendBulkSmsJob::class);
    }

    public function test_scheduled_bulk_sms_throws_exception_for_past_date(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Date must be in the future');

        $this->smsService->sendScheduledBulkSms(
            ['0712345678'],
            'Test',
            '2020-01-01 12:00:00'
        );
    }
}
