<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Moffhub\SmsHandler\Facades\Sms;
use Throwable;

class SendBulkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $recipients,
        public string $message
    ) {}

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
        }
    }

    public static function dispatchAt(array $recipients, string $message, Carbon|string $when): void
    {
        $time = $when instanceof Carbon ? $when : Carbon::parse($when);
        self::dispatch($recipients, $message)->delay($time);
    }
}
