<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use BadMethodCallException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Contracts\SmsProviderInterface;
use Moffhub\SmsHandler\Jobs\SendSmsJob;

abstract class BaseProvider implements SmsProviderInterface
{
    public function sendSms(string $to, string $message, Carbon|string|null $scheduleAt = null): ?Collection
    {
        throw new BadMethodCallException(static::class . ' must implement sendSms.');
    }

    public function sendScheduledSms(string $to, string $message, Carbon|CarbonImmutable|string $date): ?Collection
    {
        throw new BadMethodCallException(static::class . ' must implement sendScheduledSms.');
    }

    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        throw new BadMethodCallException(static::class . ' must implement sendBulkSms.');
    }

    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?Collection
    {
        throw new BadMethodCallException(static::class . ' must implement sendScheduledBulkSms.');
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return 'pending';
    }

    public function getSmsBalance(): int
    {
        return 0;
    }

    public function sendRecurringSmsViaJobs(
        string $to,
        string $message,
        Carbon|string $startDate,
        Carbon|string $endDate,
        int $intervalDays,
        bool $startImmediately = false
    ): void {
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $end = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        if ($startImmediately) {
            SendSmsJob::dispatch($to, $message);
        }

        $nextDate = $start->copy();

        while ($nextDate->lt($end)) {
            $nextDate->addDays($intervalDays);

            if ($nextDate->lte($end)) {
                SendSmsJob::dispatch($to, $message)->delay($nextDate);
            }
        }
    }
}
