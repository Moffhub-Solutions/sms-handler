<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Actions\Onfon;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Data\SmsResponseData;

class SendSmsAction
{
    /**
     * @return Collection<int, SmsResponseData>
     */
    public function execute(string $apiUrl, array $payload, string $message): Collection
    {
        $response = Http::withHeaders([
            'AccessKey' => $payload['ClientId'],
            'Content-Type' => 'application/json',
        ])->post($apiUrl, $payload);

        $responses = $response->json('Data') ?? [];

        return collect($responses)->map(fn(array $item) => new SmsResponseData(
            messageId: $item['MessageId'] ?? '',
            status: $item['MessageErrorCode'] ?? '',
            to: (string) ($item['MobileNumber'] ?? ''),
            message: $message,
            provider: 'onfon',
            response: [
                'description' => $item['MessageErrorDescription'] ?? '',
            ]
        ));
    }
}
