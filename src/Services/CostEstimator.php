<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use function Moffhub\SmsHandler\Helpers\containsUnicode;

class CostEstimator
{
    /**
     * Calculate the number of SMS segments for a message.
     *
     * GSM-7 encoding: 160 chars for single, 153 per part for multipart.
     * UCS-2 (Unicode): 70 chars for single, 67 per part for multipart.
     */
    public function calculateSegments(string $message): int
    {
        $length = mb_strlen($message);

        if ($length === 0) {
            return 0;
        }

        $isUnicode = containsUnicode($message);
        $singlePartMax = $isUnicode ? 70 : 160;
        $multiPartMax = $isUnicode ? 67 : 153;

        if ($length <= $singlePartMax) {
            return 1;
        }

        return (int) ceil($length / $multiPartMax);
    }

    /**
     * Detect whether a message contains Unicode (non-GSM-7) characters.
     */
    public function isUnicode(string $message): bool
    {
        return containsUnicode($message);
    }

    /**
     * Get the per-segment cost for a provider.
     */
    public function getPerSegmentCost(?string $provider = null): float
    {
        $provider ??= config('sms.default', 'advanta');

        $configKey = match ($provider) {
            'africastalking' => 'at',
            default => $provider,
        };

        return (float) config("sms.providers.{$configKey}.per_segment_cost", 0.0);
    }

    /**
     * Estimate the cost of sending a message.
     *
     * @param  string  $message  The SMS message
     * @param  int  $recipientCount  Number of recipients
     * @param  string|null  $provider  Provider name (defaults to configured default)
     * @return array{segments: int, per_segment_cost: float, total_cost: float, recipient_count: int, is_unicode: bool}
     */
    public function estimateCost(string $message, int $recipientCount = 1, ?string $provider = null): array
    {
        $segments = $this->calculateSegments($message);
        $perSegmentCost = $this->getPerSegmentCost($provider);
        $totalCost = $segments * $perSegmentCost * $recipientCount;

        return [
            'segments' => $segments,
            'per_segment_cost' => $perSegmentCost,
            'total_cost' => round($totalCost, 4),
            'recipient_count' => $recipientCount,
            'is_unicode' => $this->isUnicode($message),
        ];
    }
}
