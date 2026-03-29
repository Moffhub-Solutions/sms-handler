<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Actions\Advanta\SendSmsAction;
use Moffhub\SmsHandler\Exceptions\ProviderException;
use Moffhub\SmsHandler\Providers\AdvantaProvider;
use Moffhub\SmsHandler\Providers\NexmoProvider;
use Moffhub\SmsHandler\Providers\TwilioProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    // ---- Network Timeout Tests ----

    public function test_twilio_handles_network_timeout(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_test',
            authToken: 'token',
            from: '+15551234567',
        );

        $this->expectException(ConnectionException::class);
        $provider->sendSms('+254712345678', 'Test');
    }

    public function test_nexmo_handles_network_timeout(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $this->expectException(ConnectionException::class);
        $provider->sendSms('+254712345678', 'Test');
    }

    public function test_advanta_handles_network_timeout(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new AdvantaProvider(
            app: $this->app,
            apiKey: 'test_key',
            apiUrl: 'https://api.advanta.test/send',
            partnerId: 'partner',
            shortCode: 'TEST',
        );

        // Advanta catches exceptions in provider
        $result = $provider->sendSms('+254712345678', 'Test');
        $this->assertNull($result);
    }

    // ---- HTTP 500 Response Tests ----

    public function test_twilio_handles_500_response(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_test',
            authToken: 'token',
            from: '+15551234567',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_nexmo_handles_500_response(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_advanta_action_throws_on_500_response(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $action = new SendSmsAction;

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('failed to send');

        $action->execute('https://api.test/send', ['key' => 'val'], 'Test');
    }

    public function test_onfon_action_throws_on_500_response(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $action = new \Moffhub\SmsHandler\Actions\Onfon\SendSmsAction;

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('failed to send');

        $action->execute('https://api.test/send', ['ClientId' => 'test'], 'Test');
    }

    // ---- HTTP 429 Rate Limited Tests ----

    public function test_twilio_handles_429_rate_limited(): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => 20429,
                'message' => 'Too Many Requests',
            ], 429),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_test',
            authToken: 'token',
            from: '+15551234567',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_nexmo_handles_429_rate_limited(): void
    {
        Http::fake([
            '*' => Http::response([
                'type' => 'https://developer.vonage.com/api-errors#throttled',
                'title' => 'Rate Limit Hit',
            ], 429),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_advanta_action_throws_on_429_response(): void
    {
        Http::fake([
            '*' => Http::response('Rate limited', 429),
        ]);

        $action = new SendSmsAction;

        $this->expectException(ProviderException::class);

        $action->execute('https://api.test/send', ['key' => 'val'], 'Test');
    }

    // ---- Malformed JSON Response Tests ----

    public function test_twilio_handles_malformed_json(): void
    {
        Http::fake([
            '*' => Http::response('this is {not valid json', 200),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_test',
            authToken: 'token',
            from: '+15551234567',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        // Should still return a response with defaults
        $this->assertNotNull($result);
        $this->assertEquals('', $result->first()->messageId);
        $this->assertEquals('unknown', $result->first()->status);
    }

    public function test_nexmo_handles_malformed_json(): void
    {
        Http::fake([
            '*' => Http::response('not json', 200),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        // Empty messages array fallback
        $this->assertNull($result);
    }

    public function test_advanta_action_throws_on_malformed_json(): void
    {
        Http::fake([
            '*' => Http::response('not json content', 200, ['Content-Type' => 'text/plain']),
        ]);

        $action = new SendSmsAction;

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response format');

        $action->execute('https://api.test/send', ['key' => 'val'], 'Test');
    }

    public function test_onfon_action_throws_on_malformed_json(): void
    {
        Http::fake([
            '*' => Http::response('garbage response', 200, ['Content-Type' => 'text/plain']),
        ]);

        $action = new \Moffhub\SmsHandler\Actions\Onfon\SendSmsAction;

        $this->expectException(ProviderException::class);

        $action->execute('https://api.test/send', ['ClientId' => 'test'], 'Test');
    }

    // ---- Empty Response Body Tests ----

    public function test_twilio_handles_empty_response_body(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_test',
            authToken: 'token',
            from: '+15551234567',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        $this->assertNotNull($result);
        $this->assertEquals('', $result->first()->messageId);
    }

    public function test_nexmo_handles_empty_response_body(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $provider = new NexmoProvider(
            key: 'test_key',
            secret: 'test_secret',
        );

        $result = $provider->sendSms('+254712345678', 'Test');

        $this->assertNull($result);
    }

    public function test_advanta_action_throws_on_empty_response_body(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $action = new SendSmsAction;

        $this->expectException(ProviderException::class);

        $action->execute('https://api.test/send', ['key' => 'val'], 'Test');
    }

    public function test_onfon_action_throws_on_empty_response_body(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $action = new \Moffhub\SmsHandler\Actions\Onfon\SendSmsAction;

        $this->expectException(ProviderException::class);

        $action->execute('https://api.test/send', ['ClientId' => 'test'], 'Test');
    }

    // ---- Balance & Delivery Status Error Tests ----

    public function test_twilio_balance_handles_timeout(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_test',
            authToken: 'token',
            from: '+15551234567',
        );

        $this->expectException(ConnectionException::class);
        $provider->getSmsBalance();
    }

    public function test_twilio_delivery_status_handles_timeout(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new TwilioProvider(
            accountSid: 'AC_test',
            authToken: 'token',
            from: '+15551234567',
        );

        $this->expectException(ConnectionException::class);
        $provider->getSmsDeliveryStatus('SM_test');
    }

    // ---- Provider Exception Factory Tests ----

    public function test_provider_exception_unexpected_response_truncates_long_body(): void
    {
        $longBody = str_repeat('x', 600);
        $exception = ProviderException::unexpectedResponse('test', $longBody);

        $this->assertStringContainsString('Unexpected response format', $exception->getMessage());
        $this->assertStringContainsString('...', $exception->getMessage());
        // The message should be truncated
        $this->assertLessThan(600, strlen($exception->getMessage()));
    }

    public function test_provider_exception_send_failed_includes_reason(): void
    {
        $exception = ProviderException::sendFailed('twilio', 'HTTP 500: Server Error');

        $this->assertStringContainsString('twilio', $exception->getMessage());
        $this->assertStringContainsString('HTTP 500', $exception->getMessage());
    }
}
