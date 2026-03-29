<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Log;
use Moffhub\SmsHandler\SmsHandlerServiceProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class ConfigValidationTest extends TestCase
{
    public function test_warns_on_unrecognized_default_provider(): void
    {
        Log::spy();

        $this->app['config']->set('sms.default', 'invalid_provider');

        // Re-boot the service provider to trigger validation
        $this->app->getProvider(SmsHandlerServiceProvider::class)->boot();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message) {
                return str_contains($message, 'Unrecognized default provider') && str_contains($message, 'invalid_provider');
            })
            ->once();
    }

    public function test_warns_on_invalid_log_channel(): void
    {
        Log::spy();

        $this->app['config']->set('sms.log_channel', 'invalid_channel');

        $this->app->getProvider(SmsHandlerServiceProvider::class)->boot();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message) {
                return str_contains($message, 'Invalid log_channel') && str_contains($message, 'invalid_channel');
            })
            ->once();
    }

    public function test_warns_on_missing_provider_credentials(): void
    {
        Log::spy();

        $this->app['config']->set('sms.default', 'advanta');
        $this->app['config']->set('sms.providers.advanta.api_key', null);
        $this->app['config']->set('sms.providers.advanta.api_url', null);

        $this->app->getProvider(SmsHandlerServiceProvider::class)->boot();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message) {
                return str_contains($message, 'missing credentials');
            })
            ->once();
    }

    public function test_no_warning_for_valid_config(): void
    {
        Log::spy();

        $this->app['config']->set('sms.default', 'advanta');
        $this->app['config']->set('sms.log_channel', 'log');
        $this->app['config']->set('sms.providers.advanta.api_key', 'valid_key');
        $this->app['config']->set('sms.providers.advanta.api_url', 'https://api.advanta.test');

        $this->app->getProvider(SmsHandlerServiceProvider::class)->boot();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_accepts_model_as_valid_log_channel(): void
    {
        Log::spy();

        $this->app['config']->set('sms.default', 'advanta');
        $this->app['config']->set('sms.log_channel', 'model');
        $this->app['config']->set('sms.providers.advanta.api_key', 'valid_key');
        $this->app['config']->set('sms.providers.advanta.api_url', 'https://api.advanta.test');

        $this->app->getProvider(SmsHandlerServiceProvider::class)->boot();

        Log::shouldNotHaveReceived('warning');
    }
}
