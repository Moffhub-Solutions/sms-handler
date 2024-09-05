<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler;

/**
 * A helper function to format phone number
 */
function formatPhoneNumber(string $phoneNumber, string $prefix = '0', int $numberCount = -9): string
{
    return $prefix.substr($phoneNumber, -$numberCount);
}
