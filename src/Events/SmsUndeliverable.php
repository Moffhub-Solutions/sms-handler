<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsUndeliverable
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $phone,
        public readonly string $provider,
        public readonly string $reason,
        public readonly string $originalMessage,
    ) {}
}
