<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Moffhub\SmsHandler\Events\SmsUndeliverable;
use Moffhub\SmsHandler\Facades\Sms;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\Services\AdaptiveRetryStrategy;
use Throwable;

class SendBulkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries;

    public function __construct(
        public array $recipients,
        public string $message
    ) {
        $this->onQueue(config('sms.queue.name', 'default'));
        $this->timeout = (int) config('sms.queue.timeout', 30);
        $this->tries = (int) config('sms.queue.max_tries', 3);
    }

    /**
     * Get the backoff times for retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $configBackoff = config('sms.queue.backoff');

        if (is_array($configBackoff)) {
            return $configBackoff;
        }

        return [10, 30, 90];
    }

    public function handle(): void
    {
        try {
            Sms::sendBulkSms($this->recipients, $this->message);
        } catch (Throwable $exception) {
            logger()->error('Failed to send bulk SMS via job', [
                'recipients' => $this->recipients,
                'message' => $this->message,
                'error' => $exception->getMessage(),
            ]);

            $this->handleWithRetryStrategy($exception);
        }
    }

    /**
     * Apply adaptive retry strategy if enabled, otherwise re-throw for default retry.
     *
     * @throws Throwable
     */
    protected function handleWithRetryStrategy(Throwable $exception): void
    {
        $strategy = app(AdaptiveRetryStrategy::class);

        if (! $strategy->isEnabled()) {
            throw $exception;
        }

        $category = $strategy->categorize($exception);

        if (! $strategy->shouldRetry($category)) {
            $reason = match ($category) {
                AdaptiveRetryStrategy::CATEGORY_INVALID_NUMBER => 'invalid_number',
                AdaptiveRetryStrategy::CATEGORY_INSUFFICIENT_BALANCE => 'insufficient_balance',
                default => $category,
            };

            foreach ($this->recipients as $recipient) {
                event(new SmsUndeliverable(
                    phone: $recipient,
                    provider: config('sms.default', 'unknown'),
                    reason: $reason,
                    originalMessage: $this->message,
                ));
            }

            $this->fail($exception);

            return;
        }

        // Apply adaptive backoff
        $backoff = $strategy->getBackoff($category);
        $attempt = $this->attempts();

        if ($attempt <= count($backoff)) {
            $this->release($backoff[$attempt - 1]);

            return;
        }

        throw $exception;
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        logger()->error('Bulk SMS job permanently failed', [
            'recipients' => $this->recipients,
            'message' => $this->message,
            'error' => $exception?->getMessage(),
        ]);

        if (config('sms.log_channel') === 'model') {
            foreach ($this->recipients as $recipient) {
                SmsLog::where('to', $recipient)
                    ->where('message', $this->message)
                    ->where(function ($query) {
                        $query->whereNull('delivery_status')
                            ->orWhere('delivery_status', 'pending');
                    })
                    ->latest()
                    ->limit(1)
                    ->update(['delivery_status' => 'failed', 'success' => false]);
            }
        }
    }

    public static function dispatchAt(array $recipients, string $message, Carbon|string $when): void
    {
        $time = $when instanceof Carbon ? $when : Carbon::parse($when);
        self::dispatch($recipients, $message)->delay($time);
    }
}
