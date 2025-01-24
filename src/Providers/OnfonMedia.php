<?php

namespace Moffhub\SmsHandler\Providers;
use Exception;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Actions\Onfon\SendSmsAction;
use Throwable;

class OnfonMedia extends BaseProvider
{
    public function __construct(
        protected Application $app,
        protected string $apiKey,
        protected string $apiUrl,
        protected string $senderId,
        protected string $clientId,
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

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function sendSms(string $to, string $message): ?Collection
    {
        $phoneNumber = formatPhoneNumber($to, '254');

        try {
            return $this->app->make(SendSmsAction::class)->execute($this->apiUrl, [
                'ApiKey' => $this->apiKey,
                'ClientId' => $this->clientId,
                'SenderId' => $this->senderId,
                'MessageParameters' =>[
                    [
                        'Number' => $phoneNumber,
                        'Text' => $message,
                    ]
                ],
                'IsUnicode' => true,
                'IsFlash' => true,
            ], $message);

        } catch (Exception $e) {
            logger()->error($e->getMessage(), [
                'to' => $phoneNumber,
                'message' => $message,
            ]);

            return null;
        }
    }

    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        return collect($recipients)->chunk(100)->each(function ($chunk) use ($message) {
            $payload = $chunk->map(function ($recipient) use ($message) {
                return [
                    'Number' =>  formatPhoneNumber($recipient, '254'),
                    'Text' => $message,
                ];
            });
            try {
                return $this->app->make(SendSmsAction::class)->execute($this->apiUrl, [
                    'ApiKey' => $this->apiKey,
                    'ClientId' => $this->clientId,
                    'SenderId' => $this->senderId,
                    'MessageParameters' => $payload,
                    'IsUnicode' => true,
                    'IsFlash' => true,
                ], $message);

            } catch (Exception $e) {
                logger()->error($e->getMessage(), [
                    'to' => $chunk,
                    'message' => $message,
                ]);

                return null;
            }
        });
    }
}
