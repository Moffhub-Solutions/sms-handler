<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Actions\Advanta;

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
        $httpResponse = Http::post($apiUrl, $payload);
        $responses = $httpResponse->json('responses') ?? [];

        return collect($responses)->map(fn (array $item) => new SmsResponseData(
            messageId: $item['messageid'] ?? '',
            status: (string) ($item['response-code'] ?? ''),
            to: (string) ($item['mobile'] ?? ''),
            message: $message,
            provider: 'advanta',
            response: [
                'description' => $item['response-description'] ?? '',
                'networkId' => $item['networkid'] ?? '',
            ]
        ));
    }
}
