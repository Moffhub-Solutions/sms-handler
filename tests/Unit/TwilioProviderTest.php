<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Providers\TwilioProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class TwilioProviderTest extends TestCase
{
    protected TwilioProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new TwilioProvider(
            accountSid: 'AC_test_sid',
            authToken: 'test_auth_token',
            from: '+15551234567',
            apiUrl: 'https://api.twilio.test',
        );
    }

    public function test_sends_single_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'sid' => 'SM_test_123',
                'status' => 'queued',
                'date_created' => '2024-01-01T00:00:00Z',
                'price' => '-0.0075',
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Hello World');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('queued', $result->first()->status);
        $this->assertEquals('SM_test_123', $result->first()->messageId);
        $this->assertEquals('twilio', $result->first()->provider);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'AC_test_sid/Messages.json')
                && $request->data()['To'] === '+254712345678'
                && $request->data()['From'] === '+15551234567'
                && $request->data()['Body'] === 'Hello World';
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

    public function test_handles_500_server_error(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_handles_malformed_json_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'unexpected_key' => 'value',
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        // Falls back to defaults with null coalescing
        $this->assertEquals('', $result->first()->messageId);
        $this->assertEquals('unknown', $result->first()->status);
    }

    public function test_sends_bulk_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'sid' => 'SM_test_bulk',
                'status' => 'queued',
            ]),
        ]);

        $result = $this->provider->sendBulkSms(
            ['+254712345678', '+254712345679'],
            'Bulk message'
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result);
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
        $this->assertCount(1, $result);
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
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_send_scheduled_sms_with_immutable_date(): void
    {
        Queue::fake();

        $scheduleTime = CarbonImmutable::now()->addHour();
        $result = $this->provider->sendScheduledSms('+254712345678', 'Scheduled', $scheduleTime);

        $this->assertNotNull($result);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_send_scheduled_sms_with_string_date(): void
    {
        Queue::fake();

        $result = $this->provider->sendScheduledSms('+254712345678', 'Scheduled', '+1 hour');

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
        $this->assertEquals('scheduled', $result->first()->status);

        Queue::assertPushed(SendBulkSmsJob::class, function ($job) {
            return count($job->recipients) === 2;
        });
    }

    public function test_get_sms_balance_returns_balance(): void
    {
        Http::fake([
            '*' => Http::response([
                'balance' => '25.50',
                'currency' => 'USD',
            ]),
        ]);

        $balance = $this->provider->getSmsBalance();

        $this->assertEquals(25, $balance);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Balance.json');
        });
    }

    public function test_get_sms_balance_returns_zero_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $balance = $this->provider->getSmsBalance();

        $this->assertEquals(0, $balance);
    }

    public function test_get_sms_delivery_status_returns_status(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'delivered',
            ]),
        ]);

        $status = $this->provider->getSmsDeliveryStatus('SM_test_123');

        $this->assertEquals('delivered', $status);
    }

    public function test_get_sms_delivery_status_returns_unknown_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $status = $this->provider->getSmsDeliveryStatus('SM_invalid');

        $this->assertEquals('unknown', $status);
    }

    public function test_getters_return_correct_values(): void
    {
        $this->assertEquals('AC_test_sid', $this->provider->getAccountSid());
        $this->assertEquals('test_auth_token', $this->provider->getAuthToken());
        $this->assertEquals('+15551234567', $this->provider->getFrom());
        $this->assertEquals('https://api.twilio.test', $this->provider->getApiUrl());
    }
}
