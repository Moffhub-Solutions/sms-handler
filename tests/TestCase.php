<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests;

use Moffhub\SmsHandler\SmsHandlerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SmsHandlerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('sms.default', 'advanta');
        $app['config']->set('sms.providers.advanta.api_key', 'your-api-key');
        $app['config']->set('sms.providers.advanta.api_url', 'https://api.advanta.com');
    }
}
