<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Providers;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Abstract class for custom SMS providers
 * Extend this class and implement the required methods to create your own SMS provider
 */
abstract class CustomProvider extends BaseProvider
{
    /**
     * Get the API endpoint URL for the SMS provider
     */
    abstract protected function getApiUrl(): string;

    /**
     * Build the payload required by your SMS provider
     */
    abstract protected function buildPayload(string $to, string $message): array;

    /**
     * Handle and transform the API response
     * @param mixed $response The raw API response
     */
    abstract protected function handleResponse(mixed $response): ?Collection;


    /**
     * Format phone number according to provider requirements
     * Override this method if you need custom phone number formatting
     */
    protected function formatPhoneNumber(string $number): string
    {
        return formatPhoneNumber($number, '254');
    }

    /**
     * Send SMS implementation
     * Override this method if you need a completely custom implementation
     */
    public function sendSms(string $to, string $message): ?Collection
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($to);
            $payload = $this->buildPayload($phoneNumber, $message);

            $response = $this->makeHttpRequest(
                $this->getApiUrl(),
                $payload
            );

            return $this->handleResponse($response);

        } catch (\Throwable $e) {
            logger()->error($e->getMessage(), [
                'to' => $to,
                'message' => $message,
                'provider' => static::class,
            ]);

            return null;
        }
    }

    /**
     * Make the HTTP request to the SMS provider
     * Override this method if you need custom HTTP handling
     */
    protected function makeHttpRequest(string $url, array $payload): PromiseInterface|Response
    {
        return Http::post($url, $payload);
    }
}






