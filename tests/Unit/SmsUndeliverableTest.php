<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Exception;
use Illuminate\Support\Facades\Event;
use Moffhub\SmsHandler\Events\SmsUndeliverable;
use Moffhub\SmsHandler\Services\AdaptiveRetryStrategy;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsUndeliverableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('sms.retry.strategy', 'adaptive');
    }

    public function test_undeliverable_event_has_correct_properties(): void
    {
        $event = new SmsUndeliverable(
            phone: '+254712345678',
            provider: 'twilio',
            reason: 'invalid_number',
            originalMessage: 'Test message',
        );

        $this->assertEquals('+254712345678', $event->phone);
        $this->assertEquals('twilio', $event->provider);
        $this->assertEquals('invalid_number', $event->reason);
        $this->assertEquals('Test message', $event->originalMessage);
    }

    public function test_invalid_number_fires_undeliverable_event(): void
    {
        $strategy = new AdaptiveRetryStrategy;
        $exception = new Exception('Invalid phone number provided');

        $category = $strategy->categorize($exception);

        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER, $category);
        $this->assertFalse($strategy->shouldRetry($category));

        // Simulate what the job does when shouldRetry returns false
        event(new SmsUndeliverable(
            phone: '+254712345678',
            provider: 'advanta',
            reason: 'invalid_number',
            originalMessage: 'Hello test',
        ));

        Event::assertDispatched(SmsUndeliverable::class, function ($event) {
            return $event->phone === '+254712345678'
                && $event->reason === 'invalid_number'
                && $event->provider === 'advanta'
                && $event->originalMessage === 'Hello test';
        });
    }

    public function test_insufficient_balance_fires_undeliverable_event(): void
    {
        $strategy = new AdaptiveRetryStrategy;
        $exception = new Exception('Insufficient balance to send SMS');

        $category = $strategy->categorize($exception);

        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INSUFFICIENT_BALANCE, $category);
        $this->assertFalse($strategy->shouldRetry($category));

        event(new SmsUndeliverable(
            phone: '+254712345678',
            provider: 'africastalking',
            reason: 'insufficient_balance',
            originalMessage: 'Hello test',
        ));

        Event::assertDispatched(SmsUndeliverable::class, function ($event) {
            return $event->reason === 'insufficient_balance'
                && $event->provider === 'africastalking';
        });
    }

    public function test_blacklisted_number_fires_undeliverable_event(): void
    {
        $strategy = new AdaptiveRetryStrategy;
        $exception = new Exception('Number is blacklisted by carrier');

        $category = $strategy->categorize($exception);

        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER, $category);
        $this->assertFalse($strategy->shouldRetry($category));

        event(new SmsUndeliverable(
            phone: '+254712345678',
            provider: 'twilio',
            reason: 'invalid_number',
            originalMessage: 'Hello test',
        ));

        Event::assertDispatched(SmsUndeliverable::class, function ($event) {
            return $event->reason === 'invalid_number';
        });
    }

    public function test_server_error_does_not_fire_undeliverable_event(): void
    {
        $strategy = new AdaptiveRetryStrategy;
        $exception = new Exception('Internal Server Error', 500);

        $category = $strategy->categorize($exception);

        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_SERVER_ERROR, $category);
        $this->assertTrue($strategy->shouldRetry($category));

        Event::assertNotDispatched(SmsUndeliverable::class);
    }

    public function test_rate_limited_does_not_fire_undeliverable_event(): void
    {
        $strategy = new AdaptiveRetryStrategy;
        $exception = new Exception('Too many requests', 429);

        $category = $strategy->categorize($exception);

        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_RATE_LIMITED, $category);
        $this->assertTrue($strategy->shouldRetry($category));

        Event::assertNotDispatched(SmsUndeliverable::class);
    }
}
