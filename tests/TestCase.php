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
        $app['config']->set('sms.log_channel', 'log');

        $app['config']->set('sms.providers.advanta.api_key', 'test_advanta_key');
        $app['config']->set('sms.providers.advanta.api_url', 'https://api.advanta.test');
        $app['config']->set('sms.providers.advanta.partner_id', 'test_partner');
        $app['config']->set('sms.providers.advanta.short_code', 'TEST');
        $app['config']->set('sms.providers.advanta.bulk_api_url', 'https://api.advanta.test/bulk');

        $app['config']->set('sms.providers.at.username', 'sandbox');
        $app['config']->set('sms.providers.at.api_key', 'test_at_key');
        $app['config']->set('sms.providers.at.from', 'TEST');
        $app['config']->set('sms.providers.at.api_url', null);
    }
}
