<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Exception;
use Moffhub\SmsHandler\Services\AdaptiveRetryStrategy;
use Moffhub\SmsHandler\Tests\TestCase;

class AdaptiveRetryTest extends TestCase
{
    private AdaptiveRetryStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new AdaptiveRetryStrategy;
    }

    // ---- Categorization Tests ----

    public function test_categorizes_rate_limited_by_code(): void
    {
        $exception = new Exception('Request failed', 429);
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_RATE_LIMITED, $this->strategy->categorize($exception));
    }

    public function test_categorizes_rate_limited_by_message(): void
    {
        $exception = new Exception('Too many requests, rate limit exceeded');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_RATE_LIMITED, $this->strategy->categorize($exception));
    }

    public function test_categorizes_rate_limited_by_throttle_message(): void
    {
        $exception = new Exception('Request was throttled by provider');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_RATE_LIMITED, $this->strategy->categorize($exception));
    }

    public function test_categorizes_server_error_by_code(): void
    {
        $exception = new Exception('Internal Server Error', 500);
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_SERVER_ERROR, $this->strategy->categorize($exception));
    }

    public function test_categorizes_server_error_by_message(): void
    {
        $exception = new Exception('Server error occurred while processing request');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_SERVER_ERROR, $this->strategy->categorize($exception));
    }

    public function test_categorizes_server_error_by_502_code(): void
    {
        $exception = new Exception('Bad Gateway', 502);
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_SERVER_ERROR, $this->strategy->categorize($exception));
    }

    public function test_categorizes_invalid_number(): void
    {
        $exception = new Exception('Invalid phone number: +1234');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER, $this->strategy->categorize($exception));
    }

    public function test_categorizes_invalid_number_unroutable(): void
    {
        $exception = new Exception('The number is unroutable');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER, $this->strategy->categorize($exception));
    }

    public function test_categorizes_invalid_number_blacklisted(): void
    {
        $exception = new Exception('Number is blacklisted');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER, $this->strategy->categorize($exception));
    }

    public function test_categorizes_insufficient_balance(): void
    {
        $exception = new Exception('Insufficient balance to send SMS');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INSUFFICIENT_BALANCE, $this->strategy->categorize($exception));
    }

    public function test_categorizes_insufficient_credit(): void
    {
        $exception = new Exception('Not enough credit on account');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_INSUFFICIENT_BALANCE, $this->strategy->categorize($exception));
    }

    public function test_categorizes_unknown_for_generic_error(): void
    {
        $exception = new Exception('Something unexpected happened');
        $this->assertEquals(AdaptiveRetryStrategy::CATEGORY_UNKNOWN, $this->strategy->categorize($exception));
    }

    // ---- Should Retry Tests ----

    public function test_rate_limited_should_retry(): void
    {
        $this->assertTrue($this->strategy->shouldRetry(AdaptiveRetryStrategy::CATEGORY_RATE_LIMITED));
    }

    public function test_server_error_should_retry(): void
    {
        $this->assertTrue($this->strategy->shouldRetry(AdaptiveRetryStrategy::CATEGORY_SERVER_ERROR));
    }

    public function test_unknown_should_retry(): void
    {
        $this->assertTrue($this->strategy->shouldRetry(AdaptiveRetryStrategy::CATEGORY_UNKNOWN));
    }

    public function test_invalid_number_should_not_retry(): void
    {
        $this->assertFalse($this->strategy->shouldRetry(AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER));
    }

    public function test_insufficient_balance_should_not_retry(): void
    {
        $this->assertFalse($this->strategy->shouldRetry(AdaptiveRetryStrategy::CATEGORY_INSUFFICIENT_BALANCE));
    }

    // ---- Backoff Tests ----

    public function test_rate_limited_backoff_uses_longer_delays(): void
    {
        $backoff = $this->strategy->getBackoff(AdaptiveRetryStrategy::CATEGORY_RATE_LIMITED);
        $this->assertEquals([30, 60, 120], $backoff);
    }

    public function test_server_error_backoff_uses_standard_delays(): void
    {
        $backoff = $this->strategy->getBackoff(AdaptiveRetryStrategy::CATEGORY_SERVER_ERROR);
        $this->assertEquals([10, 30, 90], $backoff);
    }

    public function test_unknown_backoff_uses_standard_delays(): void
    {
        $backoff = $this->strategy->getBackoff(AdaptiveRetryStrategy::CATEGORY_UNKNOWN);
        $this->assertEquals([10, 30, 90], $backoff);
    }

    public function test_invalid_number_returns_empty_backoff(): void
    {
        $backoff = $this->strategy->getBackoff(AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER);
        $this->assertEquals([], $backoff);
    }

    public function test_insufficient_balance_returns_empty_backoff(): void
    {
        $backoff = $this->strategy->getBackoff(AdaptiveRetryStrategy::CATEGORY_INSUFFICIENT_BALANCE);
        $this->assertEquals([], $backoff);
    }

    // ---- Config Tests ----

    public function test_backoff_uses_config_values_when_set(): void
    {
        config(['sms.retry.backoff.rate_limited' => [60, 120, 240]]);
        config(['sms.retry.backoff.server_error' => [5, 15, 45]]);

        $strategy = new AdaptiveRetryStrategy;

        $this->assertEquals([60, 120, 240], $strategy->getBackoff(AdaptiveRetryStrategy::CATEGORY_RATE_LIMITED));
        $this->assertEquals([5, 15, 45], $strategy->getBackoff(AdaptiveRetryStrategy::CATEGORY_SERVER_ERROR));
    }

    public function test_max_attempts_from_config(): void
    {
        config(['sms.retry.max_attempts' => 5]);

        $strategy = new AdaptiveRetryStrategy;

        $this->assertEquals(5, $strategy->getMaxAttempts());
    }

    public function test_is_enabled_when_strategy_is_adaptive(): void
    {
        config(['sms.retry.strategy' => 'adaptive']);

        $strategy = new AdaptiveRetryStrategy;

        $this->assertTrue($strategy->isEnabled());
    }

    public function test_is_not_enabled_when_strategy_is_fixed(): void
    {
        config(['sms.retry.strategy' => 'fixed']);

        $strategy = new AdaptiveRetryStrategy;

        $this->assertFalse($strategy->isEnabled());
    }

    public function test_default_max_attempts_is_three(): void
    {
        $this->assertEquals(3, $this->strategy->getMaxAttempts());
    }
}
