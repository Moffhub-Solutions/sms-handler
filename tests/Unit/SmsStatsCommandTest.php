<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsStatsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../src/Database/Migrations');
    }

    public function test_command_runs_successfully(): void
    {
        SmsLog::create([
            'provider' => 'advanta',
            'to' => '254712345678',
            'message' => 'Test',
            'success' => true,
        ]);

        $this->artisan('sms:stats')
            ->assertExitCode(0);
    }

    public function test_command_with_provider_filter(): void
    {
        SmsLog::create([
            'provider' => 'advanta',
            'to' => '254712345678',
            'message' => 'Test',
            'success' => true,
        ]);

        $this->artisan('sms:stats --provider=advanta')
            ->assertExitCode(0);
    }

    public function test_command_with_days_option(): void
    {
        $this->artisan('sms:stats --days=7')
            ->assertExitCode(0);
    }

    public function test_command_with_no_data(): void
    {
        $this->artisan('sms:stats')
            ->assertExitCode(0);
    }
}
