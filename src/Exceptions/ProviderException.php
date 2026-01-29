<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Exceptions;

class ProviderException extends SmsException
{
    public static function notConfigured(string $provider): self
    {
        return new self("SMS provider '{$provider}' is not configured");
    }

    public static function sendFailed(string $provider, string $reason): self
    {
        return new self("SMS provider '{$provider}' failed to send: {$reason}");
    }

    public static function invalidResponse(string $provider): self
    {
        return new self("Invalid response from SMS provider '{$provider}'");
    }
}
