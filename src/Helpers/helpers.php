<?php
declare(strict_types=1);

namespace Moffhub\SmsHandler;

/**
 * A helper function to format phone number
 *
 * @param  string  $phoneNumber
 * @param  string  $prefix
 * @param  int  $numberCount
 * @return string
 */
function formatPhoneNumber(string $phoneNumber, string $prefix = '0', int $numberCount = -9): string
{
    return $prefix.substr($phoneNumber, -$numberCount);
}
