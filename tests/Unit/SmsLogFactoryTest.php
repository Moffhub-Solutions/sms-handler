<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsLogFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../src/Database/Migrations');
    }

    public function test_factory_creates_default_instance(): void
    {
        $log = SmsLog::factory()->create();

        $this->assertNotNull($log->id);
        $this->assertNotNull($log->ulid);
        $this->assertEquals('advanta', $log->provider);
        $this->assertNotNull($log->to);
        $this->assertNotNull($log->message);
        $this->assertTrue($log->success);
        $this->assertEquals('delivered', $log->delivery_status);
        $this->assertEquals(1, $log->segment_count);
    }

    public function test_factory_failed_state(): void
    {
        $log = SmsLog::factory()->failed()->create();

        $this->assertFalse($log->success);
        $this->assertEquals('failed', $log->delivery_status);
    }

    public function test_factory_delivered_state(): void
    {
        $log = SmsLog::factory()->delivered()->create();

        $this->assertTrue($log->success);
        $this->assertEquals('delivered', $log->delivery_status);
    }

    public function test_factory_pending_state(): void
    {
        $log = SmsLog::factory()->pending()->create();

        $this->assertTrue($log->success);
        $this->assertEquals('pending', $log->delivery_status);
    }

    public function test_factory_scheduled_state(): void
    {
        $log = SmsLog::factory()->scheduled()->create();

        $this->assertEquals('scheduled', $log->delivery_status);
        $this->assertNotNull($log->scheduled_at);
        $this->assertTrue($log->scheduled_at->isFuture());
    }

    public function test_factory_for_provider(): void
    {
        $log = SmsLog::factory()->forProvider('twilio')->create();

        $this->assertEquals('twilio', $log->provider);
    }

    public function test_factory_with_cost(): void
    {
        $log = SmsLog::factory()->withCost(1.50)->create();

        $this->assertEquals(1.50, $log->estimated_cost);
    }

    public function test_factory_creates_multiple(): void
    {
        $logs = SmsLog::factory()->count(5)->create();

        $this->assertCount(5, $logs);
    }

    public function test_factory_make_does_not_persist(): void
    {
        $log = SmsLog::factory()->make();

        $this->assertNull($log->id);
        $this->assertNotNull($log->provider);
        $this->assertNotNull($log->message);
    }

    public function test_factory_state_chaining(): void
    {
        $log = SmsLog::factory()
            ->forProvider('at')
            ->withCost(0.75)
            ->failed()
            ->create();

        $this->assertEquals('at', $log->provider);
        $this->assertEquals(0.75, $log->estimated_cost);
        $this->assertFalse($log->success);
        $this->assertEquals('failed', $log->delivery_status);
    }
}
