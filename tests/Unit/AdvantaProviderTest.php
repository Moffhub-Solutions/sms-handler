<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Providers\AdvantaProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class AdvantaProviderTest extends TestCase
{
    protected AdvantaProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new AdvantaProvider(
            app: $this->app,
            apiKey: 'test_api_key',
            apiUrl: 'https://api.advanta.test/send',
            partnerId: 'test_partner',
            shortCode: 'TEST',
            bulkApiUrl: 'https://api.advanta.test/bulk',
        );
    }

    public function test_sends_single_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '0712345678',
                        'messageid' => 'msg_123',
                        'networkid' => 'net_456',
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendSms('0712345678', 'Hello World');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('200', $result->first()->status);
        $this->assertEquals('msg_123', $result->first()->messageId);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), 'advanta.test/send')
                && $data['apikey'] === 'test_api_key'
                && $data['partnerID'] === 'test_partner'
                && $data['shortcode'] === 'TEST';
        });
    }

    public function test_sends_bulk_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'mobile' => '0712345678',
                        'messageid' => 'msg_1',
                    ],
                    [
                        'response-code' => 200,
                        'mobile' => '0712345679',
                        'messageid' => 'msg_2',
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendBulkSms(
            ['0712345678', '0712345679'],
            'Bulk message'
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'advanta.test/bulk');
        });
    }

    public function test_bulk_sms_falls_back_to_sequential_without_bulk_url(): void
    {
        $providerWithoutBulk = new AdvantaProvider(
            app: $this->app,
            apiKey: 'test_api_key',
            apiUrl: 'https://api.advanta.test/send',
            partnerId: 'test_partner',
            shortCode: 'TEST',
            bulkApiUrl: null,
        );

        Http::fake([
            '*' => Http::response([
                'responses' => [
                    ['response-code' => 200, 'mobile' => '0712345678', 'messageid' => 'msg_1'],
                ],
            ]),
        ]);

        $result = $providerWithoutBulk->sendBulkSms(
            ['0712345678', '0712345679'],
            'Test message'
        );

        $this->assertNotNull($result);
        Http::assertSentCount(2);
    }

    public function test_handles_api_failure_gracefully(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $result = $this->provider->sendSms('0712345678', 'Test');

        $this->assertNotNull($result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_handles_bulk_api_failure_gracefully(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $result = $this->provider->sendBulkSms(['0712345678'], 'Test');

        $this->assertNull($result);
    }

    public function test_schedules_sms_via_job_when_schedule_at_provided(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendSms('0712345678', 'Scheduled message', $scheduleTime);

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendSmsJob::class, function ($job) {
            return $job->to === '0712345678' && $job->message === 'Scheduled message';
        });
    }

    public function test_scheduled_bulk_sms_dispatches_job(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendScheduledBulkSms(
            ['0712345678', '0712345679'],
            'Bulk scheduled message',
            $scheduleTime->toImmutable()
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result);
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendBulkSmsJob::class, function ($job) {
            return count($job->recipients) === 2 && $job->message === 'Bulk scheduled message';
        });
    }

    public function test_get_sms_delivery_status_returns_pending(): void
    {
        $status = $this->provider->getSmsDeliveryStatus('msg_123');

        $this->assertEquals('pending', $status);
    }

    public function test_get_sms_balance_returns_zero(): void
    {
        $balance = $this->provider->getSmsBalance();

        $this->assertEquals(0, $balance);
    }

    public function test_getters_return_correct_values(): void
    {
        $this->assertEquals('test_api_key', $this->provider->getApiKey());
        $this->assertEquals('https://api.advanta.test/send', $this->provider->getApiUrl());
        $this->assertEquals('test_partner', $this->provider->getPartnerId());
        $this->assertEquals('TEST', $this->provider->getShortCode());
        $this->assertEquals('https://api.advanta.test/bulk', $this->provider->getBulkApiUrl());
    }
}
