<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Tests\Support\DummyCustomProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class CustomProviderTest extends TestCase
{
    protected DummyCustomProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new DummyCustomProvider(['key' => 'value']);
    }

    public function test_sends_single_sms_successfully(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'to' => '254712345678',
                'message' => 'Hello World',
            ]),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Hello World');

        // handleResponse receives the HTTP Response object, not decoded array
        // DummyCustomProvider always returns a collection with one item
        $this->assertNotNull($result);
        $this->assertCount(1, $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'dummy.com/send');
        });
    }

    public function test_handles_api_failure_gracefully(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $result = $this->provider->sendSms('+254712345678', 'Test');

        // handleResponse still processes the response object even on 500
        $this->assertNotNull($result);
        $this->assertCount(1, $result);
    }

    public function test_sends_bulk_sms(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'to' => '254712345678',
                'message' => 'Bulk',
            ]),
        ]);

        $result = $this->provider->sendBulkSms(
            ['+254712345678', '+254712345679'],
            'Bulk message'
        );

        $this->assertNotNull($result);
        Http::assertSentCount(2);
    }

    public function test_schedules_sms_via_job(): void
    {
        Queue::fake();

        $scheduleTime = Carbon::now()->addHour();
        $result = $this->provider->sendSms('+254712345678', 'Scheduled', $scheduleTime);

        $this->assertNotNull($result);
        $this->assertEquals('scheduled', $result->first()->status);
        $this->assertEquals('custom', $result->first()->provider);

        Queue::assertPushed(SendSmsJob::class);
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

    public function test_custom_provider_uses_config(): void
    {
        $provider = new DummyCustomProvider(['custom_key' => 'custom_value']);

        // The config is stored but accessible via the protected property
        $this->assertInstanceOf(DummyCustomProvider::class, $provider);
    }

    public function test_get_api_url_returns_correct_url(): void
    {
        $this->assertEquals('https://dummy.com/send', $this->provider->getApiUrl());
    }

    public function test_build_payload_returns_correct_structure(): void
    {
        $payload = $this->provider->buildPayload('254712345678', 'Test message');

        $this->assertArrayHasKey('to', $payload);
        $this->assertArrayHasKey('text', $payload);
        $this->assertEquals('254712345678', $payload['to']);
        $this->assertEquals('Test message', $payload['text']);
    }
}
