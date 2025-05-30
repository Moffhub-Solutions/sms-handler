<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Support;

use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Providers\CustomProvider;

class DummyCustomProvider extends CustomProvider
{
    public function getApiUrl(): string
    {
        return 'https://dummy.com/send';
    }

    public function buildPayload(string $to, string $message): array
    {
        return [
            'to' => $to,
            'text' => $message,
        ];
    }

    public function handleResponse(mixed $response): ?Collection
    {
        return collect([
            'status' => $response['status'] ?? 'ok',
        ]);
    }

    public function afterSend($response, $to, $message): void
    {
        // No-op for testing
    }
}
