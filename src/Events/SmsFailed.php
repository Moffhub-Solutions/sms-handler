<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SmsFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $provider,
        public readonly string $to,
        public readonly string $message,
        public readonly Throwable $exception,
    ) {}
}
