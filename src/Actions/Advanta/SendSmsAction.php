<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Actions\Advanta;

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
        $httpResponse = Http::post($apiUrl, $payload);

        $body = $httpResponse->body();
        Log::debug('Advanta SMS raw response', ['body' => $body, 'status' => $httpResponse->status()]);

        if (! $httpResponse->successful()) {
            throw ProviderException::sendFailed('advanta', "HTTP {$httpResponse->status()}: {$body}");
        }

        $json = $httpResponse->json();

        if (! is_array($json)) {
            throw ProviderException::unexpectedResponse('advanta', $body);
        }

        if (! array_key_exists('responses', $json)) {
            throw ProviderException::unexpectedResponse('advanta', $body);
        }

        $responses = $json['responses'];

        if (! is_array($responses)) {
            throw ProviderException::unexpectedResponse('advanta', $body);
        }

        return collect($responses)->map(function (mixed $item) use ($message, $body): SmsResponseData {
            if (! is_array($item)) {
                throw ProviderException::unexpectedResponse('advanta', $body);
            }

            return new SmsResponseData(
                messageId: (string) ($item['messageid'] ?? ''),
                status: (string) ($item['response-code'] ?? ''),
                to: (string) ($item['mobile'] ?? ''),
                message: $message,
                provider: 'advanta',
                response: [
                    'description' => (string) ($item['response-description'] ?? ''),
                    'networkId' => (string) ($item['networkid'] ?? ''),
                ]
            );
        });
    }
}
