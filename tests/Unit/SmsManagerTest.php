<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Foundation\Application;
use Moffhub\SmsHandler\Providers\AdvantaProvider;
use Moffhub\SmsHandler\Providers\AfricasTalkingProvider;
use Moffhub\SmsHandler\Providers\NexmoProvider;
use Moffhub\SmsHandler\Providers\TwilioProvider;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsManagerTest extends TestCase
{
    protected $app;

    protected SmsManager $smsManager;

    protected function setUp(): void
    {
        $this->app = $this->createMock(Application::class);
        $this->app->method('offsetGet')->willReturnMap([
            ['config', [
                'sms.providers.advanta.api_key' => 'advanta_api_key',
                'sms.providers.advanta.api_url' => 'advanta_api_url',
                'sms.providers.advanta.partner_id' => 'advanta_partner_id',
                'sms.providers.advanta.short_code' => 'advanta_short_code',
                'sms.providers.advanta.bulk_api_url' => 'advanta_bulk_api_url',
                'sms.providers.provider2.api_key' => 'africas_talking_api_key',
                'sms.providers.provider2.api_url' => 'africas_talking_api_url',
                'sms.default' => 'advanta',
                'sms.providers.at.api_key' => 'africas_talking_api_key',
                'sms.providers.at.api_url' => 'africas_talking_api_url',
            ]],
        ]);

        $this->smsManager = new SmsManager($this->app);
    }

    public function test_creates_advanta_driver(): void
    {
        $driver = $this->smsManager->createAdvantaDriver();
        $this->assertInstanceOf(AdvantaProvider::class, $driver);
        $this->assertEquals('advanta_api_key', $driver->getApiKey());
        $this->assertEquals('advanta_api_url', $driver->getApiUrl());
    }

    public function test_creates_africas_talking_driver(): void
    {
        $driver = $this->smsManager->createAfricasTalkingDriver();
        $this->assertInstanceOf(AfricasTalkingProvider::class, $driver);
        $this->assertEquals('africas_talking_api_key', $driver->getApiKey());
        $this->assertEquals('africas_talking_api_url', $driver->getApiUrl());
    }

    public function test_gets_default_driver(): void
    {
        $defaultDriver = $this->smsManager->getDefaultDriver();
        $this->assertEquals('advanta', $defaultDriver);
    }

    public function test_creates_nexmo_driver(): void
    {
        $this->app->method('offsetGet')->willReturnMap([
            ['config', [
                'sms.providers.nexmo.key' => 'nexmo_key',
                'sms.providers.nexmo.secret' => 'nexmo_secret',
                'sms.providers.nexmo.from' => 'nexmo_sender',
                'sms.providers.nexmo.api_url' => 'https://rest.nexmo.com/sms/json',
            ]],
        ]);

        $smsManager = new SmsManager($this->app);
        $driver = $smsManager->createNexmoDriver();

        $this->assertInstanceOf(NexmoProvider::class, $driver);
    }

    public function test_creates_twilio_driver(): void
    {
        $this->app->method('offsetGet')->willReturnMap([
            ['config', [
                'sms.providers.twilio.account_sid' => 'twilio_sid',
                'sms.providers.twilio.auth_token' => 'twilio_token',
                'sms.providers.twilio.from' => '+1234567890',
                'sms.providers.twilio.api_url' => 'https://api.twilio.com',
            ]],
        ]);

        $smsManager = new SmsManager($this->app);
        $driver = $smsManager->createTwilioDriver();

        $this->assertInstanceOf(TwilioProvider::class, $driver);
    }
}
