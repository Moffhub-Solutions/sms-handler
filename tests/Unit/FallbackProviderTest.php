<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Exception;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Data\SmsResponseData;
use Moffhub\SmsHandler\Exceptions\ProviderException;
use Moffhub\SmsHandler\Providers\BaseProvider;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;

class FallbackProviderTest extends TestCase
{
    protected SmsManager $smsManager;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Configure fallback: advanta -> failing_fallback or success_fallback
        $app['config']->set('sms.default', 'failing_primary');
        $app['config']->set('sms.providers.failing_primary.fallback', 'success_fallback');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsManager = $this->app->make(SmsManager::class);
    }

    public function test_fallback_is_used_when_primary_provider_throws_provider_exception(): void
    {
        $this->smsManager->extend('failing_primary', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                throw ProviderException::sendFailed('failing_primary', 'Connection timeout');
            }
        });

        $this->smsManager->extend('success_fallback', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                return new Collection([
                    new SmsResponseData('msg456', 'Success', '254712345678', $message, 'success_fallback'),
                ]);
            }
        });

        $service = new SmsService($this->smsManager);
        $result = $service->sendSms('0712345678', 'Test message');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('msg456', $result->first()->messageId);
    }

    public function test_returns_null_when_no_fallback_configured(): void
    {
        $this->app['config']->set('sms.providers.failing_primary.fallback', null);

        $this->smsManager->extend('failing_primary', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                throw ProviderException::sendFailed('failing_primary', 'Error');
            }
        });

        $service = new SmsService($this->smsManager);
        $result = $service->sendSms('0712345678', 'Test message');

        $this->assertNull($result);
    }

    public function test_returns_null_when_fallback_also_fails(): void
    {
        $this->smsManager->extend('failing_primary', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                throw ProviderException::sendFailed('failing_primary', 'Primary error');
            }
        });

        $this->smsManager->extend('success_fallback', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                throw ProviderException::sendFailed('success_fallback', 'Fallback error');
            }
        });

        $service = new SmsService($this->smsManager);
        $result = $service->sendSms('0712345678', 'Test message');

        $this->assertNull($result);
    }

    public function test_fallback_does_not_chain_beyond_one_level(): void
    {
        // Configure: failing_primary -> success_fallback -> third_provider
        // But success_fallback also fails; third_provider should NOT be tried
        $this->app['config']->set('sms.providers.success_fallback.fallback', 'third_provider');

        $this->smsManager->extend('failing_primary', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                throw ProviderException::sendFailed('failing_primary', 'Error');
            }
        });

        $this->smsManager->extend('success_fallback', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                throw ProviderException::sendFailed('success_fallback', 'Also failed');
            }
        });

        $thirdProviderCalled = false;
        $this->smsManager->extend('third_provider', function () use (&$thirdProviderCalled) {
            $thirdProviderCalled = true;

            return new class extends BaseProvider
            {
                public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
                {
                    return new Collection([
                        new SmsResponseData('msg789', 'Success', '254712345678', $message, 'third_provider'),
                    ]);
                }
            };
        });

        $service = new SmsService($this->smsManager);
        $result = $service->sendSms('0712345678', 'Test message');

        // Result is null because fallback also failed, and no second fallback is attempted
        $this->assertNull($result);
    }

    public function test_bulk_sms_falls_back_on_provider_exception(): void
    {
        $this->smsManager->extend('failing_primary', fn () => new class extends BaseProvider
        {
            public function sendBulkSms(array $recipients, string $message): ?Collection
            {
                throw ProviderException::sendFailed('failing_primary', 'Bulk error');
            }
        });

        $this->smsManager->extend('success_fallback', fn () => new class extends BaseProvider
        {
            public function sendBulkSms(array $recipients, string $message): ?Collection
            {
                return new Collection([
                    new SmsResponseData('msg1', 'Success', '254712345678', $message, 'success_fallback'),
                    new SmsResponseData('msg2', 'Success', '254712345679', $message, 'success_fallback'),
                ]);
            }
        });

        $service = new SmsService($this->smsManager);
        $result = $service->sendBulkSms(['0712345678', '0712345679'], 'Bulk test');

        $this->assertNotNull($result);
        $this->assertCount(2, $result);
    }

    public function test_non_provider_exception_is_rethrown(): void
    {
        $this->smsManager->extend('failing_primary', fn () => new class extends BaseProvider
        {
            public function sendSms(string $to, string $message, $scheduleAt = null): ?Collection
            {
                throw new Exception('Random error');
            }
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Random error');

        $service = new SmsService($this->smsManager);
        $service->sendSms('0712345678', 'Test message');
    }
}
