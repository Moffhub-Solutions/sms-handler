<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Moffhub\SmsHandler\Providers\AdvantaProvider;
use Moffhub\SmsHandler\Providers\AfricasTalkingProvider;
use Moffhub\SmsHandler\Providers\NexmoProvider;
use Moffhub\SmsHandler\Providers\OnfonMediaProvider;
use Moffhub\SmsHandler\Providers\TwilioProvider;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\Support\DummyCustomProvider;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsManagerTest extends TestCase
{
    protected SmsManager $smsManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsManager = $this->app->make(SmsManager::class);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('sms.default', 'advanta');
        $app['config']->set('sms.providers.advanta.api_key', 'advanta_api_key');
        $app['config']->set('sms.providers.advanta.api_url', 'https://api.advanta.com');
        $app['config']->set('sms.providers.advanta.partner_id', 'advanta_partner_id');
        $app['config']->set('sms.providers.advanta.short_code', 'advanta_short_code');
        $app['config']->set('sms.providers.advanta.bulk_api_url', 'https://api.advanta.com/bulk');

        $app['config']->set('sms.providers.at.username', 'sandbox');
        $app['config']->set('sms.providers.at.api_key', 'africas_talking_api_key');
        $app['config']->set('sms.providers.at.from', 'MYAPP');
        $app['config']->set('sms.providers.at.api_url', null);

        $app['config']->set('sms.providers.onfon.api_key', 'onfon_api_key');
        $app['config']->set('sms.providers.onfon.api_url', 'https://api.onfon.com');
        $app['config']->set('sms.providers.onfon.sender_id', 'onfon_sender');
        $app['config']->set('sms.providers.onfon.client_id', 'onfon_client_id');

        $app['config']->set('sms.providers.nexmo.key', 'nexmo_key');
        $app['config']->set('sms.providers.nexmo.secret', 'nexmo_secret');
        $app['config']->set('sms.providers.nexmo.from', 'nexmo_sender');
        $app['config']->set('sms.providers.nexmo.api_url', 'https://rest.nexmo.com/sms/json');

        $app['config']->set('sms.providers.twilio.account_sid', 'twilio_sid');
        $app['config']->set('sms.providers.twilio.auth_token', 'twilio_token');
        $app['config']->set('sms.providers.twilio.from', '+1234567890');
        $app['config']->set('sms.providers.twilio.api_url', 'https://api.twilio.com');
    }

    public function test_creates_advanta_driver(): void
    {
        $driver = $this->smsManager->createAdvantaDriver();

        $this->assertInstanceOf(AdvantaProvider::class, $driver);
        $this->assertEquals('advanta_api_key', $driver->getApiKey());
        $this->assertEquals('https://api.advanta.com', $driver->getApiUrl());
        $this->assertEquals('advanta_partner_id', $driver->getPartnerId());
        $this->assertEquals('advanta_short_code', $driver->getShortCode());
    }

    public function test_creates_africas_talking_driver(): void
    {
        $driver = $this->smsManager->createAfricasTalkingDriver();

        $this->assertInstanceOf(AfricasTalkingProvider::class, $driver);
        $this->assertEquals('sandbox', $driver->getUsername());
        $this->assertEquals('africas_talking_api_key', $driver->getApiKey());
        $this->assertEquals('MYAPP', $driver->getFrom());
        $this->assertStringContainsString('sandbox', $driver->getApiUrl());
    }

    public function test_creates_onfon_driver(): void
    {
        $driver = $this->smsManager->createOnfonMediaDriver();

        $this->assertInstanceOf(OnfonMediaProvider::class, $driver);
        $this->assertEquals('onfon_api_key', $driver->getApiKey());
        $this->assertEquals('https://api.onfon.com', $driver->getApiUrl());
        $this->assertEquals('onfon_sender', $driver->getSenderId());
        $this->assertEquals('onfon_client_id', $driver->getClientId());
    }

    public function test_creates_nexmo_driver(): void
    {
        $driver = $this->smsManager->createNexmoDriver();

        $this->assertInstanceOf(NexmoProvider::class, $driver);
        $this->assertEquals('nexmo_key', $driver->getKey());
        $this->assertEquals('nexmo_secret', $driver->getSecret());
        $this->assertEquals('nexmo_sender', $driver->getFrom());
        $this->assertEquals('https://rest.nexmo.com/sms/json', $driver->getApiUrl());
    }

    public function test_creates_twilio_driver(): void
    {
        $driver = $this->smsManager->createTwilioDriver();

        $this->assertInstanceOf(TwilioProvider::class, $driver);
        $this->assertEquals('twilio_sid', $driver->getAccountSid());
        $this->assertEquals('twilio_token', $driver->getAuthToken());
        $this->assertEquals('+1234567890', $driver->getFrom());
        $this->assertEquals('https://api.twilio.com', $driver->getApiUrl());
    }

    public function test_gets_default_driver(): void
    {
        $defaultDriver = $this->smsManager->getDefaultDriver();

        $this->assertEquals('advanta', $defaultDriver);
    }

    public function test_custom_provider_registration(): void
    {
        $this->smsManager->extend('custom_test', fn () => new DummyCustomProvider);

        $provider = $this->smsManager->driver('custom_test');

        $this->assertInstanceOf(DummyCustomProvider::class, $provider);
        $this->assertEquals('https://dummy.com/send', $provider->getApiUrl());

        $payload = $provider->buildPayload('0712345678', 'Test Message');
        $this->assertEquals([
            'to' => '0712345678',
            'text' => 'Test Message',
        ], $payload);

        $response = $provider->handleResponse(['status' => 'ok']);
        $this->assertEquals('ok', $response?->first()?->status);
    }

    public function test_africas_talking_uses_sandbox_url_for_sandbox_username(): void
    {
        $driver = $this->smsManager->createAfricasTalkingDriver();

        $this->assertStringContainsString('sandbox', $driver->getApiUrl());
    }

    public function test_driver_returns_correct_instance(): void
    {
        $driver = $this->smsManager->driver('advanta');

        $this->assertInstanceOf(AdvantaProvider::class, $driver);
    }
}
