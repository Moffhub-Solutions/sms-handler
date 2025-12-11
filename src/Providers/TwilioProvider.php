<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\Carbon;
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
        //
    }

    public function getAccountSid(): string
    {
        return $this->accountSid;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function sendSms(string $to, string $message, string|null|Carbon $scheduleAt = null): ?Collection
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
