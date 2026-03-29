<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Moffhub\SmsHandler\Events\SmsFailed;
use Moffhub\SmsHandler\Events\SmsSent;
use Moffhub\SmsHandler\Providers\AfricasTalkingProvider;
use Moffhub\SmsHandler\Providers\NexmoProvider;
use Moffhub\SmsHandler\Providers\TwilioProvider;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;

class Phase3EventsObservabilityTest extends TestCase
{
    protected SmsManager $smsManager;

    protected SmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsManager = $this->app->make(SmsManager::class);
        $this->smsService = $this->app->make(SmsService::class);
    }

    // =========================================================================
    // 3.1 Send Events
    // =========================================================================

    public function test_sms_sent_event_dispatched_on_successful_send(): void
    {
        Event::fake([SmsSent::class, SmsFailed::class]);

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

        Event::assertDispatched(SmsSent::class, function (SmsSent $event) {
            return $event->provider === 'advanta'
                && $event->to === '0712345678'
                && $event->message === 'Test message'
                && $event->messageId === 'msg123';
        });

        Event::assertNotDispatched(SmsFailed::class);
    }

    public function test_sms_failed_event_dispatched_on_null_response(): void
    {
        Event::fake([SmsSent::class, SmsFailed::class]);

        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $this->smsService->sendSms('0712345678', 'Test message');

        Event::assertDispatched(SmsFailed::class, function (SmsFailed $event) {
            return $event->provider === 'advanta'
                && $event->to === '0712345678'
                && $event->message === 'Test message';
        });
    }

    public function test_sms_failed_event_dispatched_on_bulk_sms_failure(): void
    {
        Event::fake([SmsSent::class, SmsFailed::class]);

        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $this->smsService->sendBulkSms(['0712345678', '0712345679'], 'Bulk test');

        Event::assertDispatched(SmsFailed::class);
    }

    public function test_sms_sent_event_has_correct_properties(): void
    {
        $event = new SmsSent(
            provider: 'twilio',
            to: '+254712345678',
            message: 'Hello',
            messageId: 'SM123',
            response: ['sid' => 'SM123'],
        );

        $this->assertEquals('twilio', $event->provider);
        $this->assertEquals('+254712345678', $event->to);
        $this->assertEquals('Hello', $event->message);
        $this->assertEquals('SM123', $event->messageId);
        $this->assertEquals(['sid' => 'SM123'], $event->response);
    }

    public function test_sms_failed_event_has_correct_properties(): void
    {
        $exception = new \RuntimeException('Provider timeout');
        $event = new SmsFailed(
            provider: 'nexmo',
            to: '+254712345678',
            message: 'Hello',
            exception: $exception,
        );

        $this->assertEquals('nexmo', $event->provider);
        $this->assertEquals('+254712345678', $event->to);
        $this->assertEquals('Hello', $event->message);
        $this->assertSame($exception, $event->exception);
    }

    // =========================================================================
    // 3.2 Structured Logging
    // =========================================================================

    public function test_structured_log_on_successful_sms(): void
    {
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('channel')->zeroOrMoreTimes()->andReturnSelf();
        Log::shouldReceive('getFacadeRoot')->zeroOrMoreTimes()->andReturn(Log::getFacadeRoot());

        Event::fake();

        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'mobile' => '254712345678',
                        'messageid' => 'msg123',
                    ],
                ],
            ]),
        ]);

        $this->smsService->sendSms('0712345678', 'Test structured log');

        // Verify at least one HTTP request was sent
        Http::assertSentCount(1);
    }

    public function test_sms_log_config_channel_defaults_to_null(): void
    {
        $channel = config('sms.log.channel');

        $this->assertNull($channel);
    }

    public function test_sensitive_data_scrubbed_from_logs(): void
    {
        // Test the scrub method via reflection
        $service = $this->app->make(SmsService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('scrubSensitiveData');
        $method->setAccessible(true);

        $data = [
            'provider' => 'twilio',
            'to' => '+254712345678',
            'api_key' => 'secret_key_123',
            'auth_token' => 'token_abc',
            'nested' => [
                'secret' => 'my_secret',
                'safe_value' => 'ok',
            ],
        ];

        $scrubKeys = ['api_key', 'apiKey', 'api_secret', 'auth_token', 'secret', 'token', 'password', 'key'];
        $result = $method->invoke($service, $data, $scrubKeys);

        $this->assertEquals('twilio', $result['provider']);
        $this->assertEquals('+254712345678', $result['to']);
        $this->assertEquals('***REDACTED***', $result['api_key']);
        $this->assertEquals('***REDACTED***', $result['auth_token']);
        $this->assertEquals('***REDACTED***', $result['nested']['secret']);
        $this->assertEquals('ok', $result['nested']['safe_value']);
    }

    // =========================================================================
    // 3.3 Move Hardcoded URLs to Config
    // =========================================================================

    public function test_africastalking_uses_config_base_url(): void
    {
        $provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
            baseUrl: 'https://custom-at.example.com',
        );

        $this->assertEquals('https://custom-at.example.com', $provider->getBaseUrl());
        $this->assertStringContainsString('custom-at.example.com', $provider->getApiUrl());
    }

    public function test_africastalking_defaults_to_sandbox_url(): void
    {
        $provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
        );

        $this->assertStringContainsString('sandbox', $provider->getBaseUrl());
    }

    public function test_africastalking_defaults_to_production_url(): void
    {
        $provider = new AfricasTalkingProvider(
            username: 'production_user',
            apiKey: 'test_key',
        );

        $this->assertEquals('https://api.africastalking.com', $provider->getBaseUrl());
    }

    public function test_nexmo_uses_config_base_url(): void
    {
        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
            baseUrl: 'https://custom-nexmo.example.com',
        );

        $this->assertEquals('https://custom-nexmo.example.com', $provider->getBaseUrl());
    }

    public function test_nexmo_defaults_to_production_url(): void
    {
        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $this->assertEquals('https://rest.nexmo.com', $provider->getBaseUrl());
    }

    public function test_twilio_uses_config_base_url(): void
    {
        $provider = new TwilioProvider(
            accountSid: 'AC123',
            authToken: 'token',
            from: '+1234567890',
            baseUrl: 'https://custom-twilio.example.com',
        );

        $this->assertEquals('https://custom-twilio.example.com', $provider->getBaseUrl());
    }

    public function test_twilio_defaults_to_production_url(): void
    {
        $provider = new TwilioProvider(
            accountSid: 'AC123',
            authToken: 'token',
            from: '+1234567890',
        );

        $this->assertEquals('https://api.twilio.com', $provider->getBaseUrl());
    }

    public function test_config_has_base_url_keys_for_providers(): void
    {
        $this->assertArrayHasKey('base_url', config('sms.providers.at'));
        $this->assertArrayHasKey('base_url', config('sms.providers.nexmo'));
        $this->assertArrayHasKey('base_url', config('sms.providers.twilio'));
    }

    public function test_twilio_sends_to_correct_base_url(): void
    {
        Http::fake([
            'https://custom-twilio.test/*' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ]),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_TEST',
            authToken: 'test_token',
            from: '+1234567890',
            baseUrl: 'https://custom-twilio.test',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'custom-twilio.test');
        });
    }

    public function test_nexmo_balance_uses_base_url(): void
    {
        Http::fake([
            'https://custom-nexmo.test/*' => Http::response(['value' => 25.5]),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
            baseUrl: 'https://custom-nexmo.test',
        );

        $balance = $provider->getSmsBalance();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'custom-nexmo.test/account/get-balance');
        });

        $this->assertEquals(25, $balance);
    }

    // =========================================================================
    // 3.4 Refactor Duplicate Bulk SMS Logic
    // =========================================================================

    public function test_twilio_bulk_sms_uses_base_provider_loop(): void
    {
        Http::fake([
            '*' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ]),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_TEST',
            authToken: 'test_token',
            from: '+1234567890',
        );

        $result = $provider->sendBulkSms(['+254712345678', '+254712345679'], 'Bulk message');

        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        // Should have sent 2 individual requests
        Http::assertSentCount(2);
    }

    public function test_nexmo_bulk_sms_uses_base_provider_loop(): void
    {
        Http::fake([
            '*' => Http::response([
                'messages' => [
                    [
                        'message-id' => 'msg1',
                        'status' => '0',
                        'to' => '+254712345678',
                    ],
                ],
            ]),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $result = $provider->sendBulkSms(['+254712345678', '+254712345679'], 'Bulk message');

        $this->assertNotNull($result);

        // Should have sent 2 individual requests (loop from BaseProvider)
        Http::assertSentCount(2);
    }

    public function test_africastalking_bulk_sms_uses_native_endpoint(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['number' => '+254712345678', 'status' => 'Success', 'messageId' => 'ATX1'],
                        ['number' => '+254712345679', 'status' => 'Success', 'messageId' => 'ATX2'],
                    ],
                ],
            ]),
        ]);

        $provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
        );

        $result = $provider->sendBulkSms(['+254712345678', '+254712345679'], 'Bulk test');

        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        // Should have sent only 1 request (native bulk)
        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['enqueue']) && $data['enqueue'] === 1;
        });
    }

    public function test_base_provider_bulk_sms_returns_null_when_all_fail(): void
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_TEST',
            authToken: 'test_token',
            from: '+1234567890',
        );

        $result = $provider->sendBulkSms(['+254712345678', '+254712345679'], 'Fail message');

        $this->assertNull($result);
    }

    // =========================================================================
    // 3.5 Delivery Status Polling
    // =========================================================================

    public function test_twilio_delivery_status_polling(): void
    {
        Http::fake([
            '*/Messages/SM123.json' => Http::response([
                'status' => 'delivered',
            ]),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_TEST',
            authToken: 'test_token',
            from: '+1234567890',
        );

        $status = $provider->getSmsDeliveryStatus('SM123');

        $this->assertEquals('delivered', $status);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Messages/SM123.json');
        });
    }

    public function test_twilio_delivery_status_returns_unknown_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_TEST',
            authToken: 'test_token',
            from: '+1234567890',
        );

        $status = $provider->getSmsDeliveryStatus('SM_INVALID');

        $this->assertEquals('unknown', $status);
    }

    public function test_africastalking_delivery_status_polling(): void
    {
        Http::fake([
            '*/version1/messaging*' => Http::response([
                'SMSMessageData' => [
                    'Messages' => [
                        [
                            'id' => 'ATXid_123',
                            'status' => 'Delivered',
                        ],
                    ],
                ],
            ]),
        ]);

        $provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
        );

        $status = $provider->getSmsDeliveryStatus('ATXid_123');

        $this->assertEquals('Delivered', $status);
    }

    public function test_africastalking_delivery_status_returns_pending_when_not_found(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => [
                    'Messages' => [],
                ],
            ]),
        ]);

        $provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
        );

        $status = $provider->getSmsDeliveryStatus('ATXid_UNKNOWN');

        $this->assertEquals('pending', $status);
    }

    public function test_africastalking_delivery_status_returns_unknown_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
        );

        $status = $provider->getSmsDeliveryStatus('ATXid_123');

        $this->assertEquals('unknown', $status);
    }

    public function test_nexmo_delivery_status_polling_delivered(): void
    {
        Http::fake([
            '*/search/message*' => Http::response([
                'message-id' => 'msg123',
                'status' => 'DELIVERED',
            ]),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $status = $provider->getSmsDeliveryStatus('msg123');

        $this->assertEquals('delivered', $status);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'search/message')
                && $request['id'] === 'msg123';
        });
    }

    public function test_nexmo_delivery_status_polling_failed(): void
    {
        Http::fake([
            '*/search/message*' => Http::response([
                'message-id' => 'msg123',
                'status' => 'FAILED',
            ]),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $status = $provider->getSmsDeliveryStatus('msg123');

        $this->assertEquals('failed', $status);
    }

    public function test_nexmo_delivery_status_returns_unknown_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $status = $provider->getSmsDeliveryStatus('msg_invalid');

        $this->assertEquals('unknown', $status);
    }

    public function test_nexmo_delivery_status_maps_accepted_to_sent(): void
    {
        Http::fake([
            '*/search/message*' => Http::response([
                'status' => 'ACCEPTED',
            ]),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $status = $provider->getSmsDeliveryStatus('msg123');

        $this->assertEquals('sent', $status);
    }

    public function test_nexmo_delivery_status_maps_buffered_to_pending(): void
    {
        Http::fake([
            '*/search/message*' => Http::response([
                'status' => 'BUFFERED',
            ]),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $status = $provider->getSmsDeliveryStatus('msg123');

        $this->assertEquals('pending', $status);
    }

    // =========================================================================
    // 3.5 Artisan Command
    // =========================================================================

    public function test_check_delivery_status_command_exists(): void
    {
        $this->artisan('sms:check-delivery', ['message_id' => 'test_msg_123'])
            ->assertSuccessful();
    }

    public function test_check_delivery_status_command_output(): void
    {
        Http::fake();

        $this->artisan('sms:check-delivery', ['message_id' => 'test_msg_123'])
            ->expectsOutputToContain('Checking delivery status for message: test_msg_123')
            ->expectsOutputToContain('Delivery status:')
            ->assertSuccessful();
    }

    // =========================================================================
    // 3.2 Provider logProviderRequest in BaseProvider
    // =========================================================================

    public function test_base_provider_log_provider_request_scrubs_secrets(): void
    {
        $provider = new AfricasTalkingProvider(
            username: 'sandbox',
            apiKey: 'test_key',
        );

        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('scrubSensitiveData');
        $method->setAccessible(true);

        $payload = [
            'apikey' => 'secret_123',
            'message' => 'Hello',
            'username' => 'test',
        ];

        $scrubKeys = ['api_key', 'apiKey', 'apikey', 'api_secret', 'auth_token', 'secret', 'token', 'password', 'key'];
        $result = $method->invoke($provider, $payload, $scrubKeys);

        $this->assertEquals('***REDACTED***', $result['apikey']);
        $this->assertEquals('Hello', $result['message']);
        $this->assertEquals('test', $result['username']);
    }

    // =========================================================================
    // 3.3 SmsManager passes base_url config
    // =========================================================================

    public function test_sms_manager_passes_base_url_to_africastalking(): void
    {
        $this->app['config']->set('sms.providers.at.base_url', 'https://custom-at.test');

        $manager = new SmsManager($this->app);
        $driver = $manager->createAfricasTalkingDriver();

        $this->assertEquals('https://custom-at.test', $driver->getBaseUrl());
    }

    public function test_sms_manager_passes_base_url_to_nexmo(): void
    {
        $this->app['config']->set('sms.providers.nexmo.key', 'test_key');
        $this->app['config']->set('sms.providers.nexmo.secret', 'test_secret');
        $this->app['config']->set('sms.providers.nexmo.from', 'TEST');
        $this->app['config']->set('sms.providers.nexmo.api_url', 'https://rest.nexmo.com/sms/json');
        $this->app['config']->set('sms.providers.nexmo.base_url', 'https://custom-nexmo.test');

        $manager = new SmsManager($this->app);
        $driver = $manager->createNexmoDriver();

        $this->assertEquals('https://custom-nexmo.test', $driver->getBaseUrl());
    }

    public function test_sms_manager_passes_base_url_to_twilio(): void
    {
        $this->app['config']->set('sms.providers.twilio.account_sid', 'AC_TEST');
        $this->app['config']->set('sms.providers.twilio.auth_token', 'token');
        $this->app['config']->set('sms.providers.twilio.from', '+1234567890');
        $this->app['config']->set('sms.providers.twilio.api_url', 'https://api.twilio.com');
        $this->app['config']->set('sms.providers.twilio.base_url', 'https://custom-twilio.test');

        $manager = new SmsManager($this->app);
        $driver = $manager->createTwilioDriver();

        $this->assertEquals('https://custom-twilio.test', $driver->getBaseUrl());
    }
}
