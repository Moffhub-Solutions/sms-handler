<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Data\SmsResponseData;
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
        $data = $response instanceof Response ? ($response->json() ?? []) : (is_array($response) ? $response : []);

        return collect([
            new SmsResponseData(
                messageId: 'dummy_msg_id',
                status: $data['status'] ?? 'ok',
                to: $data['to'] ?? '',
                message: $data['message'] ?? '',
                provider: 'dummy',
                response: ['raw' => $data]
            ),
        ]);
    }

    public function afterSend($response, $to, $message): void {}
}
