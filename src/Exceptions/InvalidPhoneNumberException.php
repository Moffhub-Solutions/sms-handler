<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Exceptions;

class InvalidPhoneNumberException extends SmsException
{
    public static function empty(): self
    {
        return new self('Phone number cannot be empty');
    }

    public static function invalid(string $phoneNumber, string $reason): self
    {
        return new self("Invalid phone number '{$phoneNumber}': {$reason}");
    }
}
