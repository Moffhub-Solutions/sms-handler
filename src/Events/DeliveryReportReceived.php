<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryReportReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $provider,
        public readonly string $messageId,
        public readonly ?string $status,
        public readonly ?string $phoneNumber,
        public readonly array $payload,
    ) {}
}
