<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Exceptions;

class InvalidMessageException extends SmsException
{
    public static function empty(): self
    {
        return new self('SMS message cannot be empty');
    }

    public static function tooLong(int $parts, int $maxParts): self
    {
        return new self("Message exceeds maximum of {$maxParts} SMS parts (got {$parts})");
    }
}
