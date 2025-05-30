<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class TwilioProvider extends BaseProvider
{
    public function __construct(
        protected string $accountSid,
        protected string $authToken,
        protected string $from,
        protected string $apiUrl = 'https://api.twilio.com',
    ) {
    }

    public function sendSms(string $to, string $message): ?Collection
    {
        $endpoint = "{$this->apiUrl}/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post($endpoint, [
                'To' => $to,
                'From' => $this->from,
                'Body' => $message,
            ]);

        return collect([
            'status' => $response->successful() ? 'sent' : 'failed',
            'sid' => $response->json('sid'),
        ]);
    }
}
