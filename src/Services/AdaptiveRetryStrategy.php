<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use Throwable;

class AdaptiveRetryStrategy
{
    /**
     * Error categories that determine retry behavior.
     */
    public const CATEGORY_RATE_LIMITED = 'rate_limited';

    public const CATEGORY_SERVER_ERROR = 'server_error';

    public const CATEGORY_INVALID_NUMBER = 'invalid_number';

    public const CATEGORY_INSUFFICIENT_BALANCE = 'insufficient_balance';

    public const CATEGORY_UNKNOWN = 'unknown';

    /**
     * Categorize an exception/error from a failed SMS send.
     */
    public function categorize(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());
        $code = $exception->getCode();

        // Rate limited
        if ($code === 429 || str_contains($message, 'rate limit') || str_contains($message, 'too many requests') || str_contains($message, 'throttl')) {
            return self::CATEGORY_RATE_LIMITED;
        }

        // Invalid number
        if (str_contains($message, 'invalid number')
            || str_contains($message, 'invalid phone')
            || str_contains($message, 'unroutable')
            || str_contains($message, 'not a valid phone')
            || str_contains($message, 'blacklisted')
            || str_contains($message, 'landline')
            || str_contains($message, 'is not a mobile number')) {
            return self::CATEGORY_INVALID_NUMBER;
        }

        // Insufficient balance
        if (str_contains($message, 'insufficient')
            || str_contains($message, 'balance')
            || str_contains($message, 'credit')
            || str_contains($message, 'funds')) {
            return self::CATEGORY_INSUFFICIENT_BALANCE;
        }

        // Server error
        if ($code >= 500 || str_contains($message, 'server error') || str_contains($message, 'internal error') || str_contains($message, '500') || str_contains($message, '502') || str_contains($message, '503')) {
            return self::CATEGORY_SERVER_ERROR;
        }

        return self::CATEGORY_UNKNOWN;
    }

    /**
     * Determine whether the job should retry based on the error category.
     */
    public function shouldRetry(string $category): bool
    {
        return match ($category) {
            self::CATEGORY_INVALID_NUMBER, self::CATEGORY_INSUFFICIENT_BALANCE => false,
            default => true,
        };
    }

    /**
     * Get the backoff delays (in seconds) for a given error category.
     *
     * @return array<int, int>
     */
    public function getBackoff(string $category): array
    {
        $config = config('sms.retry.backoff', []);

        return match ($category) {
            self::CATEGORY_RATE_LIMITED => $config['rate_limited'] ?? [30, 60, 120],
            self::CATEGORY_SERVER_ERROR => $config['server_error'] ?? [10, 30, 90],
            self::CATEGORY_UNKNOWN => $config['server_error'] ?? [10, 30, 90],
            default => [],
        };
    }

    /**
     * Get the maximum number of attempts from config.
     */
    public function getMaxAttempts(): int
    {
        return (int) config('sms.retry.max_attempts', 3);
    }

    /**
     * Check if the adaptive strategy is enabled.
     */
    public function isEnabled(): bool
    {
        return config('sms.retry.strategy', 'fixed') === 'adaptive';
    }
}
