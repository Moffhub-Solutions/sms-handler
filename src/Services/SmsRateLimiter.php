<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use Illuminate\Support\Facades\RateLimiter;
use Moffhub\SmsHandler\Jobs\SendSmsJob;

class SmsRateLimiter
{
    /**
     * Check if the given provider is rate-limited.
     * If so, queue the message for later delivery instead of rejecting.
     *
     * @return bool true if the message can be sent immediately, false if it was queued
     */
    public function attemptOrQueue(string $provider, string $to, string $message): bool
    {
        $rateLimit = $this->getRateLimit($provider);

        // No rate limit configured — allow immediately
        if ($rateLimit === null) {
            return true;
        }

        $key = "sms_rate_limit:{$provider}";

        $allowed = RateLimiter::attempt(
            key: $key,
            maxAttempts: $rateLimit,
            callback: fn () => true,
            decaySeconds: 60,
        );

        if (! $allowed) {
            // Queue the message for later delivery
            $retryAfter = RateLimiter::availableIn($key);
            SendSmsJob::dispatch($to, $message)->delay(now()->addSeconds($retryAfter));

            logger()->info('SMS rate-limited, queued for later delivery', [
                'provider' => $provider,
                'to' => $to,
                'retry_after_seconds' => $retryAfter,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Get the rate limit (messages per minute) for a provider.
     * Returns null if unlimited.
     */
    public function getRateLimit(string $provider): ?int
    {
        $configKey = match ($provider) {
            'africastalking' => 'at',
            default => $provider,
        };

        $limit = config("sms.providers.{$configKey}.rate_limit");

        return $limit !== null ? (int) $limit : null;
    }

    /**
     * Get the number of remaining attempts for a provider.
     */
    public function remainingAttempts(string $provider): int
    {
        $rateLimit = $this->getRateLimit($provider);

        if ($rateLimit === null) {
            return PHP_INT_MAX;
        }

        $key = "sms_rate_limit:{$provider}";

        return RateLimiter::remaining($key, $rateLimit);
    }

    /**
     * Get the number of seconds until rate limit resets for a provider.
     */
    public function availableIn(string $provider): int
    {
        $key = "sms_rate_limit:{$provider}";

        return RateLimiter::availableIn($key);
    }

    /**
     * Clear the rate limiter for a provider.
     */
    public function clear(string $provider): void
    {
        $key = "sms_rate_limit:{$provider}";
        RateLimiter::clear($key);
    }
}
