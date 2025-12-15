<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Jobs\SendBulkSmsJob;
use Moffhub\SmsHandler\Jobs\SendSmsJob;
use Throwable;

abstract class CustomProvider extends BaseProvider
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract protected function getApiUrl(): string;

    abstract protected function buildPayload(string $to, string $message): array;

    abstract protected function handleResponse(mixed $response): ?Collection;

    protected function formatPhoneNumber(string $number): string
    {
        return formatPhoneNumber($number, '254');
    }

    public function sendSms(string $to, string $message, string|null|Carbon $scheduleAt = null): ?Collection
    {
        if ($scheduleAt) {
            return $this->scheduleSmsSend($to, $message, $scheduleAt);
        }

        try {
            $formattedTo = $this->formatPhoneNumber($to);

            $this->beforeSend($formattedTo, $message);

            $payload = $this->buildPayload($formattedTo, $message);

            $response = $this->makeHttpRequest(
                $this->getApiUrl(),
                $payload
            );

            $this->afterSend($response, $formattedTo, $message);

            return $this->handleResponse($response);
        } catch (Throwable $exception) {
            $this->handleException($exception, $to, $message);

            return null;
        }
    }

    /**
     * @return Collection<int, SmsResponseData>
     */
    protected function scheduleSmsSend(string $to, string $message, Carbon|string $scheduleAt): Collection
    {
        $scheduledTime = $scheduleAt instanceof Carbon ? $scheduleAt : Carbon::parse($scheduleAt);

        SendSmsJob::dispatch($to, $message)->delay($scheduledTime);

        return collect([
            new SmsResponseData(
                messageId: '',
                status: 'scheduled',
                to: $this->formatPhoneNumber($to),
                message: $message,
                provider: 'custom',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
        ]);
    }

    public function sendScheduledSms(string $to, string $message, Carbon|CarbonImmutable|string $date): ?Collection
    {
        $scheduledTime = match (true) {
            $date instanceof CarbonImmutable => $date->toMutable(),
            $date instanceof Carbon => $date,
            default => Carbon::parse($date),
        };

        return $this->sendSms($to, $message, $scheduledTime);
    }

    public function sendBulkSms(array $recipients, string $message): ?Collection
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

    public function sendScheduledBulkSms(array $recipients, string $message, CarbonImmutable|string $date): ?Collection
    {
        $scheduledTime = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        SendBulkSmsJob::dispatch($recipients, $message)->delay($scheduledTime);

        return collect(array_map(
            fn (string $recipient) => new SmsResponseData(
                messageId: '',
                status: 'scheduled',
                to: $this->formatPhoneNumber($recipient),
                message: $message,
                provider: 'custom',
                response: ['scheduled_at' => $scheduledTime->toIso8601String()]
            ),
            $recipients
        ));
    }

    protected function makeHttpRequest(string $url, array $payload): PromiseInterface|Response
    {
        return Http::post($url, $payload);
    }

    protected function beforeSend(string $to, string $message): void {}

    protected function afterSend(mixed $response, string $to, string $message): void {}

    protected function handleException(Throwable $exception, string $to, string $message): void
    {
        logger()->error($exception->getMessage(), [
            'to' => $to,
            'message' => $message,
            'provider' => static::class,
        ]);
    }
}
