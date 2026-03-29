<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Events\SmsFailed;
use Moffhub\SmsHandler\Events\SmsSent;
use Moffhub\SmsHandler\Exceptions\InvalidMessageException;
use Moffhub\SmsHandler\Exceptions\InvalidPhoneNumberException;
use Moffhub\SmsHandler\Exceptions\ProviderException;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\SmsManager;
use Throwable;

use function Moffhub\SmsHandler\Helpers\validatePhoneNumber;
use function Moffhub\SmsHandler\Helpers\validateSmsMessage;

class SmsService
{
    protected string $logChannel;

    protected SmsRateLimiter $rateLimiter;

    protected TemplateService $templateService;

    protected CostEstimator $costEstimator;

    public function __construct(protected SmsManager $smsManager)
    {
        $this->logChannel = config('sms.log_channel', 'log');
        $this->rateLimiter = new SmsRateLimiter;
        $this->templateService = new TemplateService;
        $this->costEstimator = new CostEstimator;
    }

    /**
     * @throws Throwable
     */
    public function getSmsDeliveryStatus(string $messageId): string
    {
        return $this->smsManager->driver()->getSmsDeliveryStatus($messageId);
    }

    /**
     * @throws Throwable
     */
    public function getSmsBalance(): int
    {
        return $this->smsManager->driver()->getSmsBalance();
    }

    /**
     * Get the list of available SMS providers.
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return $this->smsManager->getAvailableProviders();
    }

    /**
     * Check if a provider is configured.
     */
    public function isProviderConfigured(string $provider): bool
    {
        return $this->smsManager->isProviderConfigured($provider);
    }

    /**
     * Get the current default provider name.
     */
    public function getDefaultProvider(): string
    {
        return $this->smsManager->getDefaultDriver();
    }

    /**
     * Render a named template and return a fluent builder.
     *
     * Usage: Sms::template('otp', ['code' => '1234'])->to($phone)->send()
     *
     * @param  string  $templateName  The template name from config
     * @param  array<string, string>  $variables  Variables to interpolate
     */
    public function template(string $templateName, array $variables = []): SmsTemplateBuilder
    {
        $message = $this->templateService->render($templateName, $variables);

        return new SmsTemplateBuilder($this, $message);
    }

    /**
     * Estimate the cost of sending a message.
     *
     * @return array{segments: int, per_segment_cost: float, total_cost: float, recipient_count: int, is_unicode: bool}
     */
    public function estimateCost(string $message, int $recipientCount = 1, ?string $provider = null): array
    {
        return $this->costEstimator->estimateCost($message, $recipientCount, $provider);
    }

    /**
     * Get an SmsAnalytics instance for querying analytics.
     */
    public function analytics(): SmsAnalytics
    {
        return new SmsAnalytics;
    }

    /**
     * Get the rate limiter instance.
     */
    public function rateLimiter(): SmsRateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Get the template service instance.
     */
    public function templateService(): TemplateService
    {
        return $this->templateService;
    }

    /**
     * Validate a phone number and throw on failure.
     *
     * @throws InvalidPhoneNumberException
     */
    protected function validatePhone(string $phoneNumber): void
    {
        $trimmed = trim($phoneNumber);

        if ($trimmed === '') {
            throw InvalidPhoneNumberException::empty();
        }

        $result = validatePhoneNumber($trimmed);

        if (! $result['valid']) {
            throw InvalidPhoneNumberException::invalid($phoneNumber, $result['error']);
        }
    }

    /**
     * Validate an SMS message and throw on failure.
     *
     * @throws InvalidMessageException
     */
    protected function validateMessage(string $message): void
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            throw InvalidMessageException::empty();
        }

        $maxLength = (int) config('sms.max_message_length', 918);
        if (mb_strlen($message) > $maxLength) {
            throw InvalidMessageException::tooLong(mb_strlen($message), $maxLength);
        }

        $result = validateSmsMessage($message);

        if (! $result['valid']) {
            throw InvalidMessageException::tooLong($result['parts'], 10);
        }
    }

    /**
     * @throws Throwable
     */
    public function sendBulkSms(array $recipients, string $message): ?Collection
    {
        $this->validateMessage($message);

        foreach ($recipients as $recipient) {
            $this->validatePhone($recipient);
        }

        $provider = $this->smsManager->driver();
        $providerName = $this->getProviderName($provider);
        $driverName = $this->smsManager->getDefaultDriver();

        try {
            $responses = $provider->sendBulkSms($recipients, $message);
        } catch (ProviderException $e) {
            // Attempt fallback for ProviderException
            $this->smsLog('sms.bulk_failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ], 'error');

            return $this->attemptFallbackBulk($driverName, $recipients, $message, $e);
        } catch (Throwable $e) {
            foreach ($recipients as $recipient) {
                $this->dispatchFailedEvent($providerName, $recipient, $message, $e);
            }

            throw $e;
        }

        if ($responses) {
            $responses->each(function (SmsResponseData $response) use ($message, $providerName) {
                $isSuccess = $this->isSuccessfulStatus($response->status);
                $this->logSms(
                    get_class($this->smsManager->driver()),
                    $response->to,
                    $message,
                    $isSuccess,
                    $response
                );

                if ($isSuccess) {
                    $this->dispatchSentEvent($providerName, $response->to, $message, $response->messageId, $response->response);
                } else {
                    $this->dispatchFailedEvent($providerName, $response->to, $message, new Exception("SMS send failed with status: {$response->status}"));
                }
            });
        } else {
            foreach ($recipients as $recipient) {
                $this->logSms(get_class($provider), $recipient, $message, false);
                $this->dispatchFailedEvent($providerName, $recipient, $message, new Exception('SMS send returned null response'));
            }
        }

        return $responses;
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function sendScheduledSms(string|array $to, string $message, Carbon|CarbonImmutable|string $date): ?Collection
    {
        $scheduledDate = match (true) {
            $date instanceof CarbonImmutable => $date->toMutable(),
            $date instanceof Carbon => $date,
            default => Carbon::parse($date),
        };

        if (! $scheduledDate->isFuture()) {
            throw new Exception('Date must be in the future');
        }

        $provider = $this->smsManager->driver();

        if (is_array($to)) {
            $responses = $provider->sendScheduledBulkSms($to, $message, CarbonImmutable::parse($scheduledDate));

            if ($responses) {
                $responses->each(function (SmsResponseData $response) use ($message, $scheduledDate) {
                    $this->logSms(
                        get_class($this->smsManager->driver()),
                        $response->to,
                        $message,
                        true,
                        $response,
                        CarbonImmutable::parse($scheduledDate)
                    );
                });
            }

            return $responses;
        }

        $responses = $provider->sendScheduledSms($to, $message, $scheduledDate);

        if ($responses) {
            $responses->each(function (SmsResponseData $response) use ($to, $message, $scheduledDate) {
                $this->logSms(
                    get_class($this->smsManager->driver()),
                    $to,
                    $message,
                    true,
                    $response,
                    CarbonImmutable::parse($scheduledDate)
                );
            });
        }

        return $responses;
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function sendScheduledBulkSms(array $recipients, string $message, Carbon|CarbonImmutable|string $date): ?Collection
    {
        $scheduledDate = match (true) {
            $date instanceof CarbonImmutable => $date,
            $date instanceof Carbon => CarbonImmutable::parse($date),
            default => CarbonImmutable::parse($date),
        };

        if (! $scheduledDate->isFuture()) {
            throw new Exception('Date must be in the future');
        }

        $provider = $this->smsManager->driver();

        $responses = $provider->sendScheduledBulkSms($recipients, $message, $scheduledDate);

        if ($responses) {
            $responses->each(function (SmsResponseData $response) use ($message, $scheduledDate) {
                $this->logSms(
                    get_class($this->smsManager->driver()),
                    $response->to,
                    $message,
                    true,
                    $response,
                    $scheduledDate
                );
            });
        }

        return $responses;
    }

    /**
     * @throws Throwable
     */
    public function sendSms(string $to, string $message): ?Collection
    {
        $this->validatePhone($to);
        $this->validateMessage($message);

        $provider = $this->smsManager->driver();
        $providerName = $this->getProviderName($provider);
        $driverName = $this->smsManager->getDefaultDriver();

        // Check rate limiting — if rate-limited, message is queued automatically
        if (! $this->rateLimiter->attemptOrQueue($driverName, $to, $message)) {
            return null;
        }

        try {
            $logs = $provider->sendSms($to, $message);
        } catch (ProviderException $e) {
            // Attempt fallback for ProviderException
            $this->smsLog('sms.failed', [
                'provider' => $providerName,
                'to' => $to,
                'error' => $e->getMessage(),
            ], 'error');

            return $this->attemptFallback($driverName, $to, $message, $e);
        } catch (Throwable $e) {
            $this->dispatchFailedEvent($providerName, $to, $message, $e);
            $this->smsLog('sms.failed', [
                'provider' => $providerName,
                'to' => $to,
                'error' => $e->getMessage(),
            ], 'error');

            throw $e;
        }

        if ($logs && $logs->isNotEmpty()) {
            $logs->each(function (SmsResponseData $response) use ($to, $message, $providerName) {
                $isSuccess = $this->isSuccessfulStatus($response->status);
                $this->logSms(
                    get_class($this->smsManager->driver()),
                    $to,
                    $message,
                    $isSuccess,
                    $response
                );

                if ($isSuccess) {
                    $this->dispatchSentEvent($providerName, $to, $message, $response->messageId, $response->response);
                    $this->smsLog('sms.sent', [
                        'provider' => $providerName,
                        'to' => $to,
                        'message_id' => $response->messageId,
                    ]);
                } else {
                    $this->dispatchFailedEvent($providerName, $to, $message, new Exception("SMS send failed with status: {$response->status}"));
                    $this->smsLog('sms.failed', [
                        'provider' => $providerName,
                        'to' => $to,
                        'status' => $response->status,
                    ], 'error');
                }
            });
        } else {
            $this->logSms(get_class($provider), $to, $message, false);
            $this->dispatchFailedEvent($providerName, $to, $message, new Exception('SMS send returned null response'));
            $this->smsLog('sms.failed', [
                'provider' => $providerName,
                'to' => $to,
                'error' => 'Null or empty response from provider',
            ], 'error');
        }

        return $logs;
    }

    /**
     * Attempt to send SMS via the fallback provider. Limited to 1 fallback (no chaining).
     *
     * @throws Throwable
     */
    protected function attemptFallback(string $primaryProvider, string $to, string $message, ProviderException $originalException): ?Collection
    {
        $fallbackProvider = $this->getFallbackProvider($primaryProvider);

        if (! $fallbackProvider) {
            $this->logSms($primaryProvider, $to, $message, false);
            $this->dispatchFailedEvent($primaryProvider, $to, $message, $originalException);

            return null;
        }

        logger()->warning('SMS fallback activated', [
            'primary_provider' => $primaryProvider,
            'fallback_provider' => $fallbackProvider,
            'original_error' => $originalException->getMessage(),
        ]);

        try {
            $provider = $this->smsManager->driver($fallbackProvider);
            $logs = $provider->sendSms($to, $message);

            if ($logs && $logs->isNotEmpty()) {
                $logs->each(function (SmsResponseData $response) use ($to, $message, $fallbackProvider) {
                    $isSuccess = $this->isSuccessfulStatus($response->status);
                    $this->logSms(
                        get_class($this->smsManager->driver($fallbackProvider)),
                        $to,
                        $message,
                        $isSuccess,
                        $response
                    );

                    if ($isSuccess) {
                        $this->dispatchSentEvent($fallbackProvider, $to, $message, $response->messageId, $response->response);
                    } else {
                        $this->dispatchFailedEvent($fallbackProvider, $to, $message, new Exception("Fallback SMS send failed with status: {$response->status}"));
                    }
                });
            } else {
                $this->logSms($fallbackProvider, $to, $message, false);
                $this->dispatchFailedEvent($fallbackProvider, $to, $message, new Exception('Fallback SMS send returned null response'));
            }

            return $logs;
        } catch (Throwable $e) {
            logger()->error('SMS fallback also failed', [
                'fallback_provider' => $fallbackProvider,
                'error' => $e->getMessage(),
            ]);

            $this->logSms($fallbackProvider, $to, $message, false);
            $this->dispatchFailedEvent($fallbackProvider, $to, $message, $e);

            return null;
        }
    }

    /**
     * Attempt to send bulk SMS via the fallback provider.
     *
     * @throws Throwable
     */
    protected function attemptFallbackBulk(string $primaryProvider, array $recipients, string $message, ProviderException $originalException): ?Collection
    {
        $fallbackProvider = $this->getFallbackProvider($primaryProvider);

        if (! $fallbackProvider) {
            foreach ($recipients as $recipient) {
                $this->logSms($primaryProvider, $recipient, $message, false);
                $this->dispatchFailedEvent($primaryProvider, $recipient, $message, $originalException);
            }

            return null;
        }

        logger()->warning('SMS bulk fallback activated', [
            'primary_provider' => $primaryProvider,
            'fallback_provider' => $fallbackProvider,
            'original_error' => $originalException->getMessage(),
        ]);

        try {
            $provider = $this->smsManager->driver($fallbackProvider);
            $responses = $provider->sendBulkSms($recipients, $message);

            if ($responses) {
                $responses->each(function (SmsResponseData $response) use ($message, $fallbackProvider) {
                    $isSuccess = $this->isSuccessfulStatus($response->status);
                    $this->logSms(
                        get_class($this->smsManager->driver($fallbackProvider)),
                        $response->to,
                        $message,
                        $isSuccess,
                        $response
                    );
                });
            } else {
                foreach ($recipients as $recipient) {
                    $this->logSms($fallbackProvider, $recipient, $message, false);
                }
            }

            return $responses;
        } catch (Throwable $e) {
            logger()->error('SMS bulk fallback also failed', [
                'fallback_provider' => $fallbackProvider,
                'error' => $e->getMessage(),
            ]);

            foreach ($recipients as $recipient) {
                $this->logSms($fallbackProvider, $recipient, $message, false);
                $this->dispatchFailedEvent($fallbackProvider, $recipient, $message, $e);
            }

            return null;
        }
    }

    /**
     * Get the fallback provider name for a given provider.
     */
    protected function getFallbackProvider(string $provider): ?string
    {
        $configKey = match ($provider) {
            'africastalking' => 'at',
            default => $provider,
        };

        $fallback = config("sms.providers.{$configKey}.fallback");

        return $fallback ? (string) $fallback : null;
    }

    protected function isSuccessfulStatus(string $status): bool
    {
        $successStatuses = [
            // AT statuses
            'Success',
            'Sent',
            'Queued',
            'Processed',
            'scheduled',
            // Advanta response codes
            '200',
            '1701',
            // Twilio statuses
            'queued',
            'sent',
            'delivered',
            // Nexmo statuses
            '0',
        ];

        return in_array($status, $successStatuses, true);
    }

    /**
     * @throws Throwable
     */
    protected function logSms(string $provider, string $to, string $message, bool $success, mixed $response = null, ?CarbonImmutable $scheduledAt = null): void
    {
        // Calculate cost estimation
        $providerShortName = $this->resolveProviderNameFromClass($provider);
        $costData = $this->costEstimator->estimateCost($message, 1, $providerShortName);

        if ($this->logChannel === 'model') {
            $smsLog = new SmsLog;
            $smsLog->provider = $provider;
            $smsLog->to = $to;
            $smsLog->message = $message;
            $smsLog->success = $success;
            $smsLog->scheduled_at = $scheduledAt;
            $smsLog->estimated_cost = $costData['total_cost'];
            $smsLog->segment_count = $costData['segments'];

            if ($response instanceof SmsResponseData) {
                $smsLog->message_id = $response->messageId ?: null;
                $smsLog->response = $response->response;
            } else {
                $smsLog->response = $response;
            }

            $smsLog->saveOrFail();
        } else {
            logger()->info('SMS sent', [
                'provider' => $provider,
                'to' => $to,
                'message' => $message,
                'success' => $success,
                'message_id' => $response instanceof SmsResponseData ? $response->messageId : null,
                'scheduled_at' => $scheduledAt?->toIso8601String(),
                'estimated_cost' => $costData['total_cost'],
                'segment_count' => $costData['segments'],
            ]);
        }
    }

    /**
     * Dispatch the SmsSent event.
     */
    protected function dispatchSentEvent(string $provider, string $to, string $message, ?string $messageId, array $response = []): void
    {
        event(new SmsSent($provider, $to, $message, $messageId, $response));
    }

    /**
     * Dispatch the SmsFailed event.
     */
    protected function dispatchFailedEvent(string $provider, string $to, string $message, Throwable $exception): void
    {
        event(new SmsFailed($provider, $to, $message, $exception));
    }

    /**
     * Get the short provider name from a provider instance.
     */
    protected function getProviderName(object $provider): string
    {
        $class = get_class($provider);

        return $this->resolveProviderNameFromClass($class) ?? $class;
    }

    /**
     * Resolve a short provider name from a fully-qualified class name.
     */
    protected function resolveProviderNameFromClass(string $providerClass): ?string
    {
        return match (true) {
            str_contains($providerClass, 'Advanta') => 'advanta',
            str_contains($providerClass, 'AfricasTalking') => 'africastalking',
            str_contains($providerClass, 'Onfon') => 'onfon',
            str_contains($providerClass, 'Nexmo') => 'nexmo',
            str_contains($providerClass, 'Twilio') => 'twilio',
            default => null,
        };
    }

    /**
     * Write a structured log entry to the configured SMS log channel.
     * Ensures no API keys/secrets are included in the context.
     */
    protected function smsLog(string $messageKey, array $context = [], string $level = 'info'): void
    {
        // Scrub any sensitive keys from the context
        $scrubKeys = ['api_key', 'apiKey', 'api_secret', 'auth_token', 'secret', 'token', 'password', 'key'];
        $context = $this->scrubSensitiveData($context, $scrubKeys);

        $logChannel = config('sms.log.channel');
        $logger = $logChannel ? Log::channel($logChannel) : Log::getFacadeRoot();

        match ($level) {
            'error' => $logger->error($messageKey, $context),
            'debug' => $logger->debug($messageKey, $context),
            'warning' => $logger->warning($messageKey, $context),
            default => $logger->info($messageKey, $context),
        };
    }

    /**
     * Recursively scrub sensitive keys from an array.
     */
    protected function scrubSensitiveData(array $data, array $scrubKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), array_map('strtolower', $scrubKeys), true)) {
                $data[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrubSensitiveData($value, $scrubKeys);
            }
        }

        return $data;
    }
}
