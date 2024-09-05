<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Foundation\Application;
use Moffhub\SmsHandler\Providers\Advanta;
use Moffhub\SmsHandler\Providers\AfricasTalking;
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
            ]],
        ]);

        $this->smsManager = new SmsManager($this->app);
    }

    public function testCreatesAdvantaDriver(): void
    {
        $driver = $this->smsManager->createAdvantaDriver();
        $this->assertInstanceOf(Advanta::class, $driver);
        $this->assertEquals('advanta_api_key', $driver->getApiKey());
        $this->assertEquals('advanta_api_url', $driver->getApiUrl());
    }

    public function testCreatesAfricasTalkingDriver(): void
    {
        $driver = $this->smsManager->createAfricasTalkingDriver();
        $this->assertInstanceOf(AfricasTalking::class, $driver);
        $this->assertEquals('africas_talking_api_key', $driver->getApiKey());
        $this->assertEquals('africas_talking_api_url', $driver->getApiUrl());
    }

    public function testGetsDefaultDriver(): void
    {
        $defaultDriver = $this->smsManager->getDefaultDriver();
        $this->assertEquals('advanta', $defaultDriver);
    }
}
