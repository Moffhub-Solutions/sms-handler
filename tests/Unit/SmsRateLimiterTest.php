<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Moffhub\SmsHandler\Services\SmsRateLimiter;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsRateLimiterTest extends TestCase
{
    protected SmsRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = new SmsRateLimiter;
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('sms.providers.advanta.rate_limit', 5);
        $app['config']->set('sms.providers.at.rate_limit', null);
    }

    public function test_returns_configured_rate_limit(): void
    {
        $this->assertEquals(5, $this->rateLimiter->getRateLimit('advanta'));
    }

    public function test_returns_null_for_unlimited_provider(): void
    {
        $this->assertNull($this->rateLimiter->getRateLimit('africastalking'));
    }

    public function test_allows_messages_within_rate_limit(): void
    {
        $this->rateLimiter->clear('advanta');

        $result = $this->rateLimiter->attemptOrQueue('advanta', '0712345678', 'Test');

        $this->assertTrue($result);
    }

    public function test_queues_messages_when_rate_limited(): void
    {
        Queue::fake();
        $this->rateLimiter->clear('advanta');

        // Exhaust the rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->attemptOrQueue('advanta', '0712345678', 'Test');
        }

        // Next attempt should be queued
        $result = $this->rateLimiter->attemptOrQueue('advanta', '0712345678', 'Test');

        $this->assertFalse($result);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_unlimited_provider_always_allows(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $result = $this->rateLimiter->attemptOrQueue('africastalking', '0712345678', 'Test');
            $this->assertTrue($result);
        }
    }

    public function test_remaining_attempts_decreases(): void
    {
        $this->rateLimiter->clear('advanta');

        $initial = $this->rateLimiter->remainingAttempts('advanta');
        $this->assertEquals(5, $initial);

        $this->rateLimiter->attemptOrQueue('advanta', '0712345678', 'Test');

        $remaining = $this->rateLimiter->remainingAttempts('advanta');
        $this->assertEquals(4, $remaining);
    }

    public function test_clear_resets_rate_limiter(): void
    {
        // Exhaust rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->attemptOrQueue('advanta', '0712345678', 'Test');
        }

        $this->rateLimiter->clear('advanta');

        $remaining = $this->rateLimiter->remainingAttempts('advanta');
        $this->assertEquals(5, $remaining);
    }

    public function test_unlimited_provider_has_max_remaining(): void
    {
        $remaining = $this->rateLimiter->remainingAttempts('africastalking');
        $this->assertEquals(PHP_INT_MAX, $remaining);
    }
}
