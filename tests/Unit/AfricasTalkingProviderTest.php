<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Providers\AfricasTalkingProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class AfricasTalkingProviderTest extends TestCase
{
    protected AfricasTalkingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_api_key',
            from: 'TESTAPP',
        );
    }

    public function test_sends_single_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => [
                    'Message' => 'Sent to 1/1 Total Cost: KES 0.8000',
                    'Recipients' => [
                        [
                            'statusCode' => 101,
                            'number' => '+254712345678',
                            'status' => 'Success',
                            'cost' => 'KES 0.8000',
                            'messageId' => 'ATXid_123456',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Hello World');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Success', $result->first()->status);
        $this->assertEquals('ATXid_123456', $result->first()->messageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sandbox')
                && $request->hasHeader('apiKey', 'test_api_key')
                && $request['username'] === 'sandbox'
                && in_array('+254712345678', $request['phoneNumbers']);
        });
    }

    public function test_sends_bulk_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => [
                    'Message' => 'Sent to 2/2 Total Cost: KES 1.6000',
                    'Recipients' => [
                        [
                            'statusCode' => 101,
                            'number' => '+254712345678',
                            'status' => 'Success',
                            'messageId' => 'ATXid_1',
                        ],
                        [
                            'statusCode' => 101,
                            'number' => '+254712345679',
                            'status' => 'Success',
                            'messageId' => 'ATXid_2',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendBulkSms(
            ['+254712345678', '+254712345679'],
            'Bulk message'
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        Http::assertSent(function ($request) {
            return $request['enqueue'] === 1
                && str_contains($request['phoneNumbers'], ',')
                && $request['senderId'] === 'TESTAPP';
        });
    }

    public function test_handles_api_failure_gracefully(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_formats_phone_numbers_correctly(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['number' => '+254712345678', 'status' => 'Success', 'messageId' => 'test'],
                    ],
                ],
            ]),
        ]);

        $this->provider->sendSms('0712345678', 'Test');

        Http::assertSent(function ($request) {
            return $request['phoneNumbers'] === ['+254712345678'];
        });
    }

    public function test_formats_phone_with_country_code(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['number' => '+254712345678', 'status' => 'Success', 'messageId' => 'test'],
                    ],
                ],
            ]),
        ]);

        $this->provider->sendSms('254712345678', 'Test');

        Http::assertSent(function ($request) {
            return $request['phoneNumbers'] === ['+254712345678'];
        });
    }

    public function test_uses_production_url_for_non_sandbox_username(): void
    {
        $productionProvider = new AfricasTalkingProvider(
            username: 'myproductionapp',
            apiKey: 'prod_api_key',
        );

        $this->assertStringNotContainsString('sandbox', $productionProvider->getApiUrl());
        $this->assertStringContainsString('api.africastalking.com', $productionProvider->getApiUrl());
    }

    public function test_includes_sender_id_when_provided(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => ['Recipients' => []],
            ]),
        ]);

        $this->provider->sendSms('+254712345678', 'Test');

        Http::assertSent(function ($request) {
            return isset($request['from']) && $request['from'] === 'TESTAPP';
        });
    }

    public function test_omits_sender_id_when_not_provided(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => ['Recipients' => []],
            ]),
        ]);

        $providerWithoutFrom = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
        );

        $providerWithoutFrom->sendSms('+254712345678', 'Test');

        Http::assertSent(function ($request) {
            return ! isset($request['from']);
        });
    }

    public function test_schedules_sms_via_job_when_schedule_at_provided(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendSms('+254712345678', 'Scheduled message', $scheduleTime);

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendSmsJob::class, function ($job) {
            return $job->to === '+254712345678' && $job->message === 'Scheduled message';
        });
    }

    public function test_scheduled_bulk_sms_dispatches_job(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendScheduledBulkSms(
            ['+254712345678', '+254712345679'],
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

    public function test_send_scheduled_sms_uses_send_sms_with_schedule(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendScheduledSms('+254712345678', 'Scheduled', $scheduleTime);

        $this->assertNotNull($result);
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_bulk_sms_uses_bulk_endpoint(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => ['Recipients' => []],
            ]),
        ]);

        $productionProvider = new AfricasTalkingProvider(
            username: 'production_user',
            apiKey: 'test_api_key',
            from: 'TESTAPP',
        );

        $productionProvider->sendBulkSms(['+254712345678'], 'Test');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/messaging/bulk');
        });
    }
}
