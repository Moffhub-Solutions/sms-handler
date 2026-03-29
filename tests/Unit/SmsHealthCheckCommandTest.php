<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsHealthCheckCommandTest extends TestCase
{
    public function test_health_check_shows_not_configured_providers(): void
    {
        $mock = $this->mock(SmsService::class);
        $mock->shouldReceive('getAvailableProviders')
            ->andReturn(['advanta', 'twilio']);
        $mock->shouldReceive('isProviderConfigured')
            ->with('advanta')
            ->andReturn(false);
        $mock->shouldReceive('isProviderConfigured')
            ->with('twilio')
            ->andReturn(false);

        $this->artisan('sms:health')
            ->expectsOutputToContain('NOT_CONFIGURED')
            ->assertExitCode(1);
    }

    public function test_health_check_with_specific_provider(): void
    {
        $mock = $this->mock(SmsService::class);
        $mock->shouldReceive('isProviderConfigured')
            ->with('advanta')
            ->andReturn(false);

        $this->artisan('sms:health', ['--provider' => 'advanta'])
            ->expectsOutputToContain('NOT_CONFIGURED')
            ->assertExitCode(1);
    }

    public function test_health_check_reports_ok_on_balance_success(): void
    {
        $mock = $this->mock(SmsService::class);
        $mock->shouldReceive('getAvailableProviders')
            ->andReturn(['advanta']);
        $mock->shouldReceive('isProviderConfigured')
            ->with('advanta')
            ->andReturn(true);
        $mock->shouldReceive('getSmsBalance')
            ->andReturn(100);

        $this->artisan('sms:health')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    }

    public function test_health_check_reports_fail_on_balance_error(): void
    {
        $mock = $this->mock(SmsService::class);
        $mock->shouldReceive('getAvailableProviders')
            ->andReturn(['twilio']);
        $mock->shouldReceive('isProviderConfigured')
            ->with('twilio')
            ->andReturn(true);
        $mock->shouldReceive('getSmsBalance')
            ->andThrow(new \Exception('Auth failed'));

        $this->artisan('sms:health')
            ->expectsOutputToContain('FAIL')
            ->assertExitCode(1);
    }
}
