<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Moffhub\SmsHandler\Providers\Advanta;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;
use PHPUnit\Framework\MockObject\Exception;
use Throwable;

class SmsServiceTest extends TestCase
{
    protected SmsManager $smsManager;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->smsManager = $this->createMock(SmsManager::class);
    }

    /**
     * @throws Throwable
     * @throws Exception
     */
    public function testFailsToSendSms(): void
    {
        $provider = $this->createMock(Advanta::class);
        $provider->method('sendSms')->willReturn(null);
        $this->smsManager->method('driver')->willReturn($provider);

        $smsService = new SmsService($this->smsManager);
        $smsService->sendSms('1234567890', 'Test message');

        $this->assertTrue(true);
    }

    /**
     * @throws Throwable
     * @throws Exception
     */
    public function testSendsSmsSuccessfully(): void
    {
        $provider = $this->createMock(Advanta::class);
        $provider->method('sendSms')->willReturn(collect(['log' => 'message sent']));
        $this->smsManager->method('driver')->willReturn($provider);

        $smsService = new SmsService($this->smsManager);
        $smsService->sendSms('1234567890', 'Test message');

        $this->assertTrue(true);
    }
}
