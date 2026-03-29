<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Events;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboundSmsReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly string $provider,
        public readonly ?string $providerMessageId,
        public readonly CarbonImmutable $receivedAt,
        public readonly array $rawPayload,
    ) {}
}
