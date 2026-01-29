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
 * Validate a phone number format.
 *
 * @param  string  $phoneNumber  The phone number to validate
 * @param  int  $minLength  Minimum length of digits (default 9)
 * @param  int  $maxLength  Maximum length of digits (default 15, ITU-T E.164 max)
 * @return array{valid: bool, cleaned: string, digits: int, error: string|null}
 */
function validatePhoneNumber(string $phoneNumber, int $minLength = 9, int $maxLength = 15): array
{
    // Remove all non-numeric characters except +
    $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

    // Remove leading + for digit count
    $digits = ltrim($cleaned, '+');
    $digitCount = strlen($digits);

    if ($digitCount === 0) {
        return [
            'valid' => false,
            'cleaned' => $cleaned,
            'digits' => 0,
            'error' => 'Phone number contains no digits',
        ];
    }

    if ($digitCount < $minLength) {
        return [
            'valid' => false,
            'cleaned' => $cleaned,
            'digits' => $digitCount,
            'error' => "Phone number must have at least {$minLength} digits",
        ];
    }

    if ($digitCount > $maxLength) {
        return [
            'valid' => false,
            'cleaned' => $cleaned,
            'digits' => $digitCount,
            'error' => "Phone number must not exceed {$maxLength} digits",
        ];
    }

    return [
        'valid' => true,
        'cleaned' => $cleaned,
        'digits' => $digitCount,
        'error' => null,
    ];
}

/**
 * Validate an SMS message.
 *
 * @param  string  $message  The message to validate
 * @param  int  $maxParts  Maximum number of SMS parts allowed (default 10)
 * @return array{valid: bool, length: int, parts: int, unicode: bool, error: string|null}
 */
function validateSmsMessage(string $message, int $maxParts = 10): array
{
    $length = mb_strlen($message);

    if ($length === 0) {
        return [
            'valid' => false,
            'length' => 0,
            'parts' => 0,
            'unicode' => false,
            'error' => 'Message cannot be empty',
        ];
    }

    // Detect unicode by checking for non-GSM characters
    $unicode = containsUnicode($message);
    $lengthInfo = validateSmsLength($message, $unicode);

    if ($lengthInfo['parts'] > $maxParts) {
        return [
            'valid' => false,
            'length' => $length,
            'parts' => $lengthInfo['parts'],
            'unicode' => $unicode,
            'error' => "Message exceeds maximum of {$maxParts} SMS parts",
        ];
    }

    return [
        'valid' => true,
        'length' => $length,
        'parts' => $lengthInfo['parts'],
        'unicode' => $unicode,
        'error' => null,
    ];
}

/**
 * Check if a string contains non-GSM 7-bit characters (unicode).
 */
function containsUnicode(string $message): bool
{
    // GSM 7-bit basic character set
    $gsm7bit = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ ÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    // GSM 7-bit extension characters (require 2 bytes)
    $gsm7bitExt = '^{}\\[~]|€';

    $allGsm = $gsm7bit.$gsm7bitExt;

    for ($i = 0; $i < mb_strlen($message); $i++) {
        $char = mb_substr($message, $i, 1);
        if (mb_strpos($allGsm, $char) === false) {
            return true;
        }
    }

    return false;
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
