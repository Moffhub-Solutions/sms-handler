<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\Services\SmsAnalytics;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected SmsAnalytics $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analytics = new SmsAnalytics;
    }

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

    /**
     * Create an SmsLog with a specific created_at timestamp.
     */
    protected function createLog(array $attributes): SmsLog
    {
        $log = new SmsLog;
        $log->forceFill(array_merge([
            'provider' => 'advanta',
            'to' => '254712345678',
            'message' => 'Test',
            'success' => true,
        ], $attributes));
        $log->save();

        return $log;
    }

    public function test_summary_returns_correct_counts(): void
    {
        $this->createLog(['to' => '254712345678', 'success' => true]);
        $this->createLog(['to' => '254712345679', 'success' => true]);
        $this->createLog(['to' => '254712345680', 'success' => false]);

        $summary = $this->analytics->summary();

        $this->assertEquals(3, $summary['total_sent']);
        $this->assertEquals(2, $summary['total_delivered']);
        $this->assertEquals(1, $summary['total_failed']);
        $this->assertEquals(66.67, $summary['success_rate']);
    }

    public function test_summary_empty_returns_zeros(): void
    {
        $summary = $this->analytics->summary();

        $this->assertEquals(0, $summary['total_sent']);
        $this->assertEquals(0, $summary['total_delivered']);
        $this->assertEquals(0, $summary['total_failed']);
        $this->assertEquals(0.0, $summary['success_rate']);
    }

    public function test_for_provider_filters_correctly(): void
    {
        $this->createLog(['provider' => 'advanta']);
        $this->createLog(['provider' => 'twilio']);

        $summary = $this->analytics->forProvider('advanta')->summary();

        $this->assertEquals(1, $summary['total_sent']);
        $this->assertEquals('advanta', $summary['provider']);
    }

    public function test_last_days_filters_correctly(): void
    {
        // Recent log
        $this->createLog([
            'message' => 'Recent',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        // Old log
        $this->createLog([
            'message' => 'Old',
            'created_at' => Carbon::now()->subDays(40),
        ]);

        $summary = $this->analytics->last30Days()->summary();

        $this->assertEquals(1, $summary['total_sent']);
    }

    public function test_last7_days_alias(): void
    {
        $this->createLog([
            'message' => 'Recent',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $this->createLog([
            'message' => 'Older',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $summary = $this->analytics->last7Days()->summary();

        $this->assertEquals(1, $summary['total_sent']);
    }

    public function test_between_date_range(): void
    {
        $this->createLog([
            'message' => 'In range',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->createLog([
            'message' => 'Out of range',
            'created_at' => Carbon::now()->subDays(20),
        ]);

        $summary = $this->analytics->between(
            Carbon::now()->subDays(10),
            Carbon::now()
        )->summary();

        $this->assertEquals(1, $summary['total_sent']);
    }

    public function test_chaining_provider_and_date(): void
    {
        $this->createLog([
            'provider' => 'advanta',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $this->createLog([
            'provider' => 'twilio',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $summary = $this->analytics->forProvider('advanta')->last7Days()->summary();

        $this->assertEquals(1, $summary['total_sent']);
        $this->assertEquals('advanta', $summary['provider']);
    }

    public function test_daily_breakdown(): void
    {
        $this->createLog([
            'success' => true,
            'created_at' => Carbon::today()->setHour(10),
        ]);

        $this->createLog([
            'success' => false,
            'created_at' => Carbon::today()->setHour(11),
        ]);

        $breakdown = $this->analytics->dailyBreakdown();

        $this->assertCount(1, $breakdown);
        $this->assertEquals(2, $breakdown->first()['sent']);
        $this->assertEquals(1, $breakdown->first()['delivered']);
        $this->assertEquals(1, $breakdown->first()['failed']);
    }

    public function test_per_provider_summary(): void
    {
        $this->createLog(['provider' => 'advanta', 'success' => true]);
        $this->createLog(['provider' => 'twilio', 'success' => false]);

        $providerSummary = $this->analytics->perProviderSummary();

        $this->assertCount(2, $providerSummary);

        $advanta = $providerSummary->firstWhere('provider', 'advanta');
        $this->assertEquals(1, $advanta['sent']);
        $this->assertEquals(1, $advanta['delivered']);
        $this->assertEquals(100.0, $advanta['success_rate']);

        $twilio = $providerSummary->firstWhere('provider', 'twilio');
        $this->assertEquals(1, $twilio['sent']);
        $this->assertEquals(0, $twilio['delivered']);
        $this->assertEquals(0.0, $twilio['success_rate']);
    }

    public function test_immutability_of_scoped_queries(): void
    {
        $base = $this->analytics;
        $scoped = $base->forProvider('advanta');

        // Base should not be modified
        $baseSummary = $base->summary();
        $this->assertNull($baseSummary['provider']);

        $scopedSummary = $scoped->summary();
        $this->assertEquals('advanta', $scopedSummary['provider']);
    }
}
