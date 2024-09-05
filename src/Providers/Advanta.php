<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use SendSmsAction;
use SmsResponseData;
use Throwable;

use function Moffhub\SmsHandler\formatPhoneNumber;

class Advanta extends BaseProvider
{
    public function __construct(
        protected Application $app,
        protected string $apiKey,
        protected string $apiUrl,
        protected string $partnerId,
        protected string $shortCode,
        protected ?string $bulkApiUrl = null,
    ) {
        ///
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getPartnerId(): string
    {
        return $this->partnerId;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function getBulkApiUrl(): ?string
    {
        return $this->bulkApiUrl;
    }

    public function getSmsDeliveryStatus(string $messageId): string
    {
        return 'delivered';
    }

    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        collect($recipients)->map(function ($recipient, $message) {
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
            $responses = $response->json('responses');

            return collect($responses)->map(function ($response) {
                return [
                    'responseCode' => $response['response-code'],
                    'responseDescription' => $response['response-description'],
                    'mobile' => $response['mobile'],
                    'messageId' => $response['messageid'],
                    //                    'clientSmsId' => $response['clientsmsid'],
                    'networkId' => $response['networkid'],
                ];
            });
        });

        return null;
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendScheduledSms(string $to, string $message, CarbonImmutable|string $date): ?Collection
    {
        try {
            return $this->app->make(SendSmsAction::class)->execute($this->apiUrl, [
                'apikey' => $this->apiKey,
                'message' => $message,
                'mobile' => $to,
                'partnerID' => $this->partnerId,
                'shortcode' => $this->shortCode,
                'sendtime' => $date->format('Y-m-d H:i:s'),
            ], $message);

        } catch (Throwable $e) {
            logger()->error($e->getMessage(), [
                'to' => $to,
                'message' => $message,
            ]);

            return null;
        }
    }

    /**
     * Send SMS
     *
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendSms(string $to, string $message): ?Collection
    {
        $phoneNumber = formatPhoneNumber($to);

        try {
            return $this->app->make(SendSmsAction::class)->execute($this->apiUrl, [
                'apikey' => $this->apiKey,
                'message' => $message,
                'mobile' => $phoneNumber,
                'partnerID' => $this->partnerId,
                'shortcode' => $this->shortCode,
            ], $message);

        } catch (Exception $e) {
            logger()->error($e->getMessage(), [
                'to' => $phoneNumber,
                'message' => $message,
            ]);

            return null;
        }
    }
}
