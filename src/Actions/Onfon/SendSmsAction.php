<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Actions\Onfon;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Exceptions\ProviderException;

class SendSmsAction
{
    /**
     * @return Collection<int, SmsResponseData>
     *
     * @throws ProviderException
     */
    public function execute(string $apiUrl, array $payload, string $message): Collection
    {
        $response = Http::withHeaders([
            'AccessKey' => $payload['ClientId'] ?? '',
            'Content-Type' => 'application/json',
        ])->post($apiUrl, $payload);

        $body = $response->body();
        Log::debug('Onfon SMS raw response', ['body' => $body, 'status' => $response->status()]);

        if (! $response->successful()) {
            throw ProviderException::sendFailed('onfon', "HTTP {$response->status()}: {$body}");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw ProviderException::unexpectedResponse('onfon', $body);
        }

        if (! array_key_exists('Data', $json)) {
            throw ProviderException::unexpectedResponse('onfon', $body);
        }

        $responses = $json['Data'];

        if (! is_array($responses)) {
            throw ProviderException::unexpectedResponse('onfon', $body);
        }

        return collect($responses)->map(function (mixed $item) use ($message, $body): SmsResponseData {
            if (! is_array($item)) {
                throw ProviderException::unexpectedResponse('onfon', $body);
            }

            return new SmsResponseData(
                messageId: (string) ($item['MessageId'] ?? ''),
                status: (string) ($item['MessageErrorCode'] ?? ''),
                to: (string) ($item['MobileNumber'] ?? ''),
                message: $message,
                provider: 'onfon',
                response: [
                    'description' => (string) ($item['MessageErrorDescription'] ?? ''),
                ]
            );
        });
    }
}
