<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Providers\OnfonMediaProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class OnfonMediaProviderTest extends TestCase
{
    protected OnfonMediaProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new OnfonMediaProvider(
            app: $this->app,
            apiKey: 'test_onfon_key',
            apiUrl: 'https://api.onfon.test/send',
            senderId: 'TESTSENDER',
            clientId: 'test_client_id',
        );
    }

    public function test_sends_single_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [
                    [
                        'MessageId' => 'onfon_msg_123',
                        'MessageErrorCode' => '0',
                        'MobileNumber' => '254712345678',
                        'MessageErrorDescription' => 'Success',
                    ],
                ],
            ]),
        ]);

        $result = $this->provider->sendSms('0712345678', 'Hello World');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('0', $result->first()->status);
        $this->assertEquals('onfon_msg_123', $result->first()->messageId);
        $this->assertEquals('onfon', $result->first()->provider);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'onfon.test')
                && $request->hasHeader('AccessKey', 'test_client_id');
        });
    }

    public function test_returns_null_on_api_failure(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->provider->sendSms('0712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_handles_malformed_response_missing_data_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'unexpected' => 'response',
            ]),
        ]);

        $result = $this->provider->sendSms('0712345678', 'Test');

        // The action throws ProviderException which is caught by the provider
        $this->assertNull($result);
    }

    public function test_handles_empty_data_array(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [],
            ]),
        ]);

        $result = $this->provider->sendSms('0712345678', 'Test');

        $this->assertNotNull($result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_sends_bulk_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [
                    [
                        'MessageId' => 'onfon_bulk_1',
                        'MessageErrorCode' => '0',
                        'MobileNumber' => '254712345678',
                        'MessageErrorDescription' => 'Success',
                    ],
                    [
                        'MessageId' => 'onfon_bulk_2',
                        'MessageErrorCode' => '0',
                        'MobileNumber' => '254712345679',
                        'MessageErrorDescription' => 'Success',
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
    }

    public function test_bulk_sms_returns_null_when_all_fail(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Error'], 500),
        ]);

        $result = $this->provider->sendBulkSms(
            ['0712345678'],
            'Test'
        );

        $this->assertNull($result);
    }

    public function test_schedules_sms_via_job(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendSms('0712345678', 'Scheduled', $scheduleTime);

        $this->assertNotNull($result);
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendSmsJob::class, function ($job) {
            return $job->to === '0712345678' && $job->message === 'Scheduled';
        });
    }

    public function test_send_scheduled_sms(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendScheduledSms('0712345678', 'Scheduled', $scheduleTime);

        $this->assertNotNull($result);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_send_scheduled_sms_with_immutable(): void
    {
        Queue::fake();

        $scheduleTime = CarbonImmutable::now()->addHour();
        $result = $this->provider->sendScheduledSms('0712345678', 'Test', $scheduleTime);

        $this->assertNotNull($result);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_scheduled_bulk_sms_dispatches_job(): void
    {
        Queue::fake();

        $scheduleTime = CarbonImmutable::now()->addHour();
        $result = $this->provider->sendScheduledBulkSms(
            ['0712345678', '0712345679'],
            'Bulk scheduled',
            $scheduleTime
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        Queue::assertPushed(SendBulkSmsJob::class);
    }

    public function test_get_sms_balance_returns_zero(): void
    {
        // OnfonMedia doesn't implement balance check
        $balance = $this->provider->getSmsBalance();

        $this->assertEquals(0, $balance);
    }

    public function test_get_sms_delivery_status_returns_pending(): void
    {
        $status = $this->provider->getSmsDeliveryStatus('onfon_msg_123');

        $this->assertEquals('pending', $status);
    }

    public function test_getters_return_correct_values(): void
    {
        $this->assertEquals('test_onfon_key', $this->provider->getApiKey());
        $this->assertEquals('https://api.onfon.test/send', $this->provider->getApiUrl());
        $this->assertEquals('TESTSENDER', $this->provider->getSenderId());
        $this->assertEquals('test_client_id', $this->provider->getClientId());
    }
}
