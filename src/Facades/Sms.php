<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Facades;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection|null sendSms(string $phoneNumber, string $message)
 * @method static Collection|null sendBulkSms(array $phoneNumbers, string $message)
 * @method static Collection|null sendScheduledSms(string|array $to, string $message, Carbon|CarbonImmutable|string $date)
 * @method static Collection|null sendScheduledBulkSms(array $recipients, string $message, Carbon|CarbonImmutable|string $date)
 * @method static string getSmsDeliveryStatus(string $messageId)
 * @method static int getSmsBalance()
 * @method static array getAvailableProviders()
 * @method static bool isProviderConfigured(string $provider)
 * @method static string getDefaultProvider()
 * @method static \Moffhub\SmsHandler\Services\SmsTemplateBuilder template(string $templateName, array $variables = [])
 * @method static array estimateCost(string $message, int $recipientCount = 1, ?string $provider = null)
 * @method static \Moffhub\SmsHandler\Services\SmsAnalytics analytics()
 * @method static \Moffhub\SmsHandler\Services\SmsRateLimiter rateLimiter()
 * @method static \Moffhub\SmsHandler\Services\TemplateService templateService()
 *
 * @see \Moffhub\SmsHandler\Services\SmsService
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms';
    }
}
