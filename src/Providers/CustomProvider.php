<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
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

    public function sendSms(string $to, string $message): ?Collection
    {
        try {
            $to = $this->formatPhoneNumber($to);

            $this->beforeSend($to, $message);

            $payload = $this->buildPayload($to, $message);

            $response = $this->makeHttpRequest(
                $this->getApiUrl(),
                $payload
            );

            $this->afterSend($response, $to, $message);

            return $this->handleResponse($response);
        } catch (Throwable $e) {
            $this->handleException($e, $to, $message);
            return null;
        }
    }

    protected function makeHttpRequest(string $url, array $payload): PromiseInterface|Response
    {
        return Http::post($url, $payload);
    }

    // 👇 New extensibility points

    protected function beforeSend(string $to, string $message): void
    {
        // Subclasses may log or transform here
    }

    protected function afterSend(mixed $response, string $to, string $message): void
    {
        // Subclasses may log, audit, or store response
    }

    protected function handleException(Throwable $e, string $to, string $message): void
    {
        logger()->error($e->getMessage(), [
            'to' => $to,
            'message' => $message,
            'provider' => static::class,
        ]);
    }
}
