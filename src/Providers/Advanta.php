<?php

namespace Moffhub\SmsHandler\Providers;

use Exception;
use Illuminate\Support\Facades\Http;

class Advanta extends BaseProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $apiUrl,
        protected string $partnerId,
        protected string $shortCode,
        protected ?string $bulkApiUrl = null,
    ) {
        //
    }

    public function sendSms(string $to, string $message): ?object
    {
        try {
            $response = Http::post($this->apiUrl, [
                'apikey' => $this->apiKey,
                'message' => $message,
                'mobile' => $to,
                'partnerID' => $this->partnerId,
                'shortcode' => $this->shortCode,
            ]);
            $responses = $response->json('responses');

            return collect($responses)->map(function ($response) {
                return [
                    'responseCode' => $response['response-code'],
                    'responseDescription' => $response['response-description'],
                    'mobile' => $response['mobile'],
                    'messageId' => $response['messageid'],
                    'clientSmsId' => $response['clientsmsid'],
                    'networkId' => $response['networkid'],
                ];
            });
        } catch (Exception $e) {
            //TODO: Log the exception
            logger()->error($e->getMessage(), [
                'to' => $to,
                'message' => $message,
            ]);

            return null;
        }
    }

    public function sendBulkSms(array $recipients, string $message): ?object
    {
        $recipients = collect($recipients)->map(function ($recipient, $message) {
            return [
                'mobile' => $recipient,
                'apikey' => $this->apiKey,
                'partnerID' => $this->partnerId,
                'shortcode' => $this->shortCode,
                'pass_type' => 'plain',
                'clientsmsid' => '123456',
                'message' => $message,
            ];
        })->chunk(20)->each(function ($chunk) {
            $response = Http::post($this->bulkApiUrl, $chunk);
            $response = $response->json('responses');
            foreach ($response as $key => $value) {
                if ($key === 'status') {
                    if ($value === 'success') {
                        return $response;
                    }
                }
            }

            return $response;
        });

        return null;
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return 'delivered';
    }
}
