<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class NexmoProvider extends BaseProvider
{
    public function __construct(
        protected string $key,
        protected string $secret,
        protected string $from = 'NEXMO',
        protected string $apiUrl = 'https://rest.nexmo.com/sms/json',
    ) {
        //
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function sendSms(string $to, string $message): ?Collection
    {
        $response = Http::post($this->apiUrl, [
            'api_key' => $this->key,
            'api_secret' => $this->secret,
            'to' => $to,
            'from' => $this->from,
            'text' => $message,
        ]);

        $body = $response->json();

        return collect([
            'status' => $body['messages'][0]['status'] ?? 'unknown',
            'message_id' => $body['messages'][0]['message-id'] ?? null,
        ]);
    }
}
