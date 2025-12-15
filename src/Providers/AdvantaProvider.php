<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Actions\Advanta\SendSmsAction;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;

use function Moffhub\SmsHandler\Helpers\formatPhoneNumber;

class AdvantaProvider extends BaseProvider
{
    public function __construct(
        protected Application $app,
        protected string $apiKey,
        protected string $apiUrl,
        protected string $partnerId,
        protected string $shortCode,
        protected ?string $bulkApiUrl = null,
    ) {}

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

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        if (! $this->bulkApiUrl) {
            return $this->sendBulkSmsSequentially($recipients, $message);
        }

        $allResponses = collect();

        collect($recipients)->map(fn (string $recipient) => [
            'mobile' => formatPhoneNumber($recipient),
            'apikey' => $this->apiKey,
            'partnerID' => $this->partnerId,
            'shortcode' => $this->shortCode,
            'pass_type' => 'plain',
            'clientsmsid' => uniqid('sms_'),
            'message' => $message,
        ])->chunk(20)->each(function (Collection $chunk) use (&$allResponses, $message) {
            $response = Http::post($this->bulkApiUrl, $chunk->values()->toArray());
            $responses = $response->json('responses') ?? [];

            $mapped = collect($responses)->map(fn (array $item) => new SmsResponseData(
                messageId: $item['messageid'] ?? '',
                status: (string) ($item['response-code'] ?? ''),
                to: (string) ($item['mobile'] ?? ''),
                message: $message,
                provider: 'advanta',
                response: [
                    'description' => $item['response-description'] ?? '',
                    'networkId' => $item['networkid'] ?? '',
                ]
            ));

            $allResponses = $allResponses->merge($mapped);
        });

        return $allResponses->isEmpty() ? null : $allResponses;
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    protected function sendBulkSmsSequentially(array $recipients, string $message): ?Collection
    {
        $responses = collect();

        foreach ($recipients as $recipient) {
            $result = $this->sendSms($recipient, $message);
            if ($result) {
                $responses = $responses->merge($result);
            }
        }

        return $responses->isEmpty() ? null : $responses;
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendScheduledSms(string $to, string $message, CarbonImmutable|string|Carbon $date): ?Collection
    {
        $scheduledTime = match (true) {
            $date instanceof CarbonImmutable => $date->toMutable(),
            $date instanceof Carbon => $date,
            default => Carbon::parse($date),
        };

        return $this->sendSms($to, $message, $scheduledTime);
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?Collection
    {
        $scheduledTime = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        SendBulkSmsJob::dispatch($recipients, $message)->delay($scheduledTime);

        return collect(array_map(
            fn (string $recipient) => new SmsResponseData(
                messageId: '',
                status: 'scheduled',
                to: formatPhoneNumber($recipient),
                message: $message,
                provider: 'advanta',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
            $recipients
        ));
    }

    /**
     * @return Collection<int, SmsResponseData>|null
     */
    public function sendSms(string $to, string $message, Carbon|string|null $scheduleAt = null): ?Collection
    {
        $formattedPhone = formatPhoneNumber($to);

        if ($scheduleAt) {
            $scheduledTime = $scheduleAt instanceof Carbon ? $scheduleAt : Carbon::parse($scheduleAt);

            SendSmsJob::dispatch($to, $message)->delay($scheduledTime);

            return collect([
                new SmsResponseData(
                    messageId: '',
                    status: 'scheduled',
                    to: $formattedPhone,
                    message: $message,
                    provider: 'advanta',
                    response: ['scheduled_at' => $scheduledTime->toIso8601String()]
                ),
            ]);
        }

        try {
            return $this->app->make(SendSmsAction::class)->execute($this->apiUrl, [
                'apikey' => $this->apiKey,
                'message' => $message,
                'mobile' => $formattedPhone,
                'partnerID' => $this->partnerId,
                'shortcode' => $this->shortCode,
            ], $message);
        } catch (Exception $exception) {
            logger()->error($exception->getMessage(), [
                'to' => $formattedPhone,
                'message' => $message,
            ]);

            return null;
        }
    }
}
