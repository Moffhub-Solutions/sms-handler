<?php
declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SendSmsAction
{
    /**
     * @param string $apiUrl
     * @param array $payload
     * @param string $message
     * @return Collection<int, SmsResponseData>
     */
    public function execute(string $apiUrl, array $payload, string $message): Collection
    {
        $response = Http::post($apiUrl, $payload);
        $responses = $response->json('responses');

        return collect($responses)->map(function ($response) use ($message) {
            return new SmsResponseData(
                messageId: $response['messageid'],
                status: $response['response-code'],
                to: $response['mobile'],
                message: $message,
                provider: 'advanta',
                response: [
                    'description' => $response['response-description'],
                    'networkId' => $response['networkid'],
                ]
            );
        });
    }
}
