<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Moffhub\SmsHandler\Services\CostEstimator;
use Moffhub\SmsHandler\Tests\TestCase;

class CostEstimatorTest extends TestCase
{
    protected CostEstimator $costEstimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costEstimator = new CostEstimator;
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('sms.providers.advanta.per_segment_cost', 1.50);
        $app['config']->set('sms.providers.at.per_segment_cost', 0.80);
    }

    public function test_single_gsm7_segment(): void
    {
        $message = 'Hello world'; // 11 chars, well under 160
        $segments = $this->costEstimator->calculateSegments($message);

        $this->assertEquals(1, $segments);
    }

    public function test_exactly_160_chars_is_one_segment(): void
    {
        $message = str_repeat('A', 160);
        $segments = $this->costEstimator->calculateSegments($message);

        $this->assertEquals(1, $segments);
    }

    public function test_161_chars_is_two_segments_multipart(): void
    {
        $message = str_repeat('A', 161);
        $segments = $this->costEstimator->calculateSegments($message);

        // 161 / 153 = 2 (multipart uses 153 per segment)
        $this->assertEquals(2, $segments);
    }

    public function test_306_chars_is_two_segments(): void
    {
        $message = str_repeat('A', 306);
        $segments = $this->costEstimator->calculateSegments($message);

        // 306 / 153 = 2.0 = 2 segments
        $this->assertEquals(2, $segments);
    }

    public function test_307_chars_is_three_segments(): void
    {
        $message = str_repeat('A', 307);
        $segments = $this->costEstimator->calculateSegments($message);

        $this->assertEquals(3, $segments);
    }

    public function test_unicode_single_segment_70_chars(): void
    {
        // Use emoji which is UCS-2
        $message = str_repeat("\u{1F600}", 70); // 70 emoji chars
        $segments = $this->costEstimator->calculateSegments($message);

        $this->assertEquals(1, $segments);
    }

    public function test_unicode_multipart_71_chars(): void
    {
        $message = str_repeat("\u{1F600}", 71);
        $segments = $this->costEstimator->calculateSegments($message);

        // 71 / 67 = 2 (multipart uses 67 per segment)
        $this->assertEquals(2, $segments);
    }

    public function test_empty_message_returns_zero_segments(): void
    {
        $this->assertEquals(0, $this->costEstimator->calculateSegments(''));
    }

    public function test_is_unicode_detects_emoji(): void
    {
        $this->assertTrue($this->costEstimator->isUnicode("Hello \u{1F600}"));
        $this->assertFalse($this->costEstimator->isUnicode('Hello world'));
    }

    public function test_get_per_segment_cost(): void
    {
        $this->assertEquals(1.50, $this->costEstimator->getPerSegmentCost('advanta'));
        $this->assertEquals(0.80, $this->costEstimator->getPerSegmentCost('africastalking'));
    }

    public function test_get_per_segment_cost_defaults_to_zero(): void
    {
        $this->assertEquals(0.0, $this->costEstimator->getPerSegmentCost('nexmo'));
    }

    public function test_estimate_cost_single_recipient(): void
    {
        $result = $this->costEstimator->estimateCost('Hello world', 1, 'advanta');

        $this->assertEquals(1, $result['segments']);
        $this->assertEquals(1.50, $result['per_segment_cost']);
        $this->assertEquals(1.50, $result['total_cost']);
        $this->assertEquals(1, $result['recipient_count']);
        $this->assertFalse($result['is_unicode']);
    }

    public function test_estimate_cost_multiple_recipients(): void
    {
        $result = $this->costEstimator->estimateCost('Hello world', 5, 'advanta');

        $this->assertEquals(1, $result['segments']);
        $this->assertEquals(7.50, $result['total_cost']); // 1 segment * 1.50 * 5
        $this->assertEquals(5, $result['recipient_count']);
    }

    public function test_estimate_cost_multipart_message(): void
    {
        $message = str_repeat('A', 161); // 2 segments
        $result = $this->costEstimator->estimateCost($message, 1, 'advanta');

        $this->assertEquals(2, $result['segments']);
        $this->assertEquals(3.0, $result['total_cost']); // 2 * 1.50
    }

    public function test_estimate_cost_uses_default_provider(): void
    {
        $result = $this->costEstimator->estimateCost('Hello', 1, null);

        $this->assertEquals(1, $result['segments']);
        $this->assertEquals(1.50, $result['per_segment_cost']); // advanta is default
    }
}
