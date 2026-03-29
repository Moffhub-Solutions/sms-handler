<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Providers\NexmoProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class NexmoProviderTest extends TestCase
{
    protected NexmoProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new NexmoProvider(
            key: 'test_nexmo_key',
            secret: 'test_nexmo_secret',
            from: 'TESTAPP',
            apiUrl: 'https://rest.nexmo.test/sms/json',
        );
    }

    public function test_sends_single_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'message-count' => '1',
                'messages' => [
                    [
                        'to' => '+254712345678',
                        'message-id' => 'nexmo_msg_123',
                        'status' => '0',
                        'remaining-balance' => '10.50',
                        'message-price' => '0.03',
                        'network' => '23410',
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Hello World');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('sent', $result->first()->status);
        $this->assertEquals('nexmo_msg_123', $result->first()->messageId);
        $this->assertEquals('nexmo', $result->first()->provider);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), 'nexmo.test')
                && $data['api_key'] === 'test_nexmo_key'
                && $data['api_secret'] === 'test_nexmo_secret'
                && $data['to'] === '+254712345678'
                && $data['from'] === 'TESTAPP';
        });
    }

    public function test_returns_null_on_api_failure(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_returns_null_on_empty_messages(): void
    {
        Http::fake([
            '*' => Http::response([
                'message-count' => '0',
                'messages' => [],
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_handles_failed_message_status(): void
    {
        Http::fake([
            '*' => Http::response([
                'message-count' => '1',
                'messages' => [
                    [
                        'to' => '+254712345678',
                        'message-id' => 'nexmo_fail',
                        'status' => '4',
                        'error-text' => 'Invalid credentials',
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        $this->assertNotNull($result);
        $this->assertEquals('failed', $result->first()->status);
    }

    public function test_handles_malformed_response_missing_messages_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'unexpected' => 'data',
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_sends_bulk_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'message-count' => '1',
                'messages' => [
                    [
                        'to' => '+254712345678',
                        'message-id' => 'nexmo_bulk_1',
                        'status' => '0',
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendBulkSms(
            ['+254712345678', '+254712345679'],
            'Bulk message'
        );

        $this->assertNotNull($result);
        Http::assertSentCount(2);
    }

    public function test_bulk_sms_returns_null_when_all_fail(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Error'], 500),
        ]);

        $result = $this->provider->sendBulkSms(
            ['+254712345678'],
            'Test'
        );

        $this->assertNull($result);
    }

    public function test_schedules_sms_via_job(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendSms('+254712345678', 'Scheduled', $scheduleTime);

        $this->assertNotNull($result);
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendSmsJob::class, function ($job) {
            return $job->to === '+254712345678' && $job->message === 'Scheduled';
        });
    }

    public function test_send_scheduled_sms(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendScheduledSms('+254712345678', 'Scheduled', $scheduleTime);

        $this->assertNotNull($result);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_send_scheduled_sms_with_immutable(): void
    {
        Queue::fake();

        $scheduleTime = CarbonImmutable::now()->addHour();
        $result = $this->provider->sendScheduledSms('+254712345678', 'Test', $scheduleTime);

        $this->assertNotNull($result);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_scheduled_bulk_sms_dispatches_job(): void
    {
        Queue::fake();

        $scheduleTime = CarbonImmutable::now()->addHour();
        $result = $this->provider->sendScheduledBulkSms(
            ['+254712345678', '+254712345679'],
            'Bulk scheduled',
            $scheduleTime
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        Queue::assertPushed(SendBulkSmsJob::class);
    }

    public function test_get_sms_balance_returns_balance(): void
    {
        Http::fake([
            '*' => Http::response([
                'value' => 10.75,
                'autoReload' => false,
            ]),
        ]);

        $balance = $this->provider->getSmsBalance();

        $this->assertEquals(10, $balance);
    }

    public function test_get_sms_balance_returns_zero_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Auth failed'], 401),
        ]);

        $balance = $this->provider->getSmsBalance();

        $this->assertEquals(0, $balance);
    }

    public function test_get_sms_delivery_status_returns_delivered(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'DELIVERED',
            ]),
        ]);

        $status = $this->provider->getSmsDeliveryStatus('nexmo_msg_123');

        $this->assertEquals('delivered', $status);
    }

    public function test_get_sms_delivery_status_returns_unknown_on_failure(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $status = $this->provider->getSmsDeliveryStatus('nexmo_invalid');

        $this->assertEquals('unknown', $status);
    }

    public function test_getters_return_correct_values(): void
    {
        $this->assertEquals('test_nexmo_key', $this->provider->getKey());
        $this->assertEquals('test_nexmo_secret', $this->provider->getSecret());
        $this->assertEquals('TESTAPP', $this->provider->getFrom());
        $this->assertEquals('https://rest.nexmo.test/sms/json', $this->provider->getApiUrl());
    }
}
