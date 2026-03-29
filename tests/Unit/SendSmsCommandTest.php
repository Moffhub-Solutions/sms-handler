<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\Tests\TestCase;

class SendSmsCommandTest extends TestCase
{
    public function test_dry_run_validates_without_sending(): void
    {
        $this->artisan('sms:send', [
            'phone' => '254712345678',
            'message' => 'Hello test',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run mode')
            ->expectsOutputToContain('Validation passed')
            ->assertExitCode(0);
    }

    public function test_send_fails_gracefully_on_provider_error(): void
    {
        $mock = $this->mock(SmsService::class);
        $mock->shouldReceive('getDefaultProvider')->andReturn('advanta');
        $mock->shouldReceive('sendSms')
            ->andThrow(new \Exception('Connection refused'));

        $this->artisan('sms:send', [
            'phone' => '254712345678',
            'message' => 'Hello test',
        ])
            ->expectsOutputToContain('Failed to send SMS')
            ->assertExitCode(1);
    }

    public function test_send_reports_failure_on_null_response(): void
    {
        $mock = $this->mock(SmsService::class);
        $mock->shouldReceive('getDefaultProvider')->andReturn('advanta');
        $mock->shouldReceive('sendSms')
            ->andReturn(null);

        $this->artisan('sms:send', [
            'phone' => '254712345678',
            'message' => 'Hello test',
        ])
            ->expectsOutputToContain('SMS send returned no response')
            ->assertExitCode(1);
    }

    public function test_send_succeeds_with_valid_response(): void
    {
        $response = new SmsResponseData(
            messageId: 'msg_123',
            status: 'sent',
            to: '254712345678',
            message: 'Hello test',
            provider: 'advanta',
            response: [],
        );

        $mock = $this->mock(SmsService::class);
        $mock->shouldReceive('getDefaultProvider')->andReturn('advanta');
        $mock->shouldReceive('sendSms')
            ->andReturn(collect([$response]));

        $this->artisan('sms:send', [
            'phone' => '254712345678',
            'message' => 'Hello test',
        ])
            ->expectsOutputToContain('SMS sent successfully')
            ->assertExitCode(0);
    }
}
