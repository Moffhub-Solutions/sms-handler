<?php

namespace Moffhub\SmsHandler\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Moffhub\SmsHandler\Facades\Sms;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $message;

    public function __construct(string $to, string $message)
    {
        $this->to = $to;
        $this->message = $message;
    }

    public function handle(): void
    {
        try {
            Sms::sendSms($this->to, $this->message);
        } catch (Throwable $e) {
            logger()->error('Failed to send SMS via job', [
                'to' => $this->to,
                'message' => $this->message,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
