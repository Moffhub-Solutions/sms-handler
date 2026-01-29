<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Helpers;

/**
 * Format a phone number with the specified country prefix.
 *
 * @param  string  $phoneNumber  The phone number to format
 * @param  string  $prefix  The country prefix (e.g., '0', '254', '+254')
 * @param  int  $numberCount  Number of digits from the end to take (default -9 for Kenya)
 */
function formatPhoneNumber(string $phoneNumber, string $prefix = '0', int $numberCount = -9): string
{
    // Remove common non-numeric characters
    $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

    // If number already starts with the prefix (with or without +), return as is
    $prefixWithPlus = str_starts_with($prefix, '+') ? $prefix : '+'.$prefix;
    $prefixWithoutPlus = ltrim($prefix, '+');

    if (str_starts_with($cleaned, $prefixWithPlus) || str_starts_with($cleaned, $prefixWithoutPlus)) {
        // Return with prefix format requested
        if (str_starts_with($prefix, '+')) {
            return str_starts_with($cleaned, '+') ? $cleaned : '+'.$cleaned;
        }

        return ltrim($cleaned, '+');
    }

    // Handle numbers starting with 0
    if (str_starts_with($cleaned, '0')) {
        $cleaned = substr($cleaned, 1);
    }

    // Take the last N digits and prepend the prefix
    $digits = substr($cleaned, $numberCount);

    return $prefix.$digits;
}

/**
 * Validate SMS message length.
 *
 * @param  string  $message  The SMS message
 * @param  bool  $unicode  Whether message contains unicode characters
 * @return array{valid: bool, length: int, parts: int, max_length: int}
 */
function validateSmsLength(string $message, bool $unicode = false): array
{
    $length = mb_strlen($message);

    // GSM 7-bit: 160 chars single, 153 per part for multipart
    // Unicode: 70 chars single, 67 per part for multipart
    $singlePartMax = $unicode ? 70 : 160;
    $multiPartMax = $unicode ? 67 : 153;

    if ($length <= $singlePartMax) {
        $parts = 1;
    } else {
        $parts = (int) ceil($length / $multiPartMax);
    }

    return [
        'valid' => $parts <= 10, // Most providers limit to 10 parts
        'length' => $length,
        'parts' => $parts,
        'max_length' => $singlePartMax,
    ];
}
