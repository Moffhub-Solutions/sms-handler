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
        $response = Http::post($apiUrl, $payload)->withAddedHeader('AccessKey', $payload['ClientId']);
        $responses = $response->json('Data');

        return collect($responses)->map(function ($response) use ($message) {
            return new SmsResponseData(
                messageId: $response['MessageId'] ?? '',
                status: '',
                to: (string) $response['MobileNumber'] ?? '',
                message: $message,
                provider: 'onfon',
                response: [
                    'description' => '',
                    'networkId' => '',
                ]
            );
        });
    }
}
