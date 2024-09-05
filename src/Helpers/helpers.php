<?php

declare(strict_types=1);

/**
 * A helper function to format phone number
 */
function formatPhoneNumber(string $phoneNumber, string $prefix = '0', int $numberCount = -9): string
{
    return $prefix.substr($phoneNumber, $numberCount);
}
