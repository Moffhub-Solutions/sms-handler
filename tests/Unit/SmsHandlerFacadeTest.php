<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Moffhub\SmsHandler\Facades\Sms;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsHandlerFacadeTest extends TestCase
{
    public function testSendsSmsFacadeSuccessfully(): void
    {
        Sms::shouldReceive('sendSms')
            ->once()
            ->with('1234567890', 'Test message')
            ->andReturn(true);

        Sms::sendSms('1234567890', 'Test message');
    }

    public function testFailsToSendSmsFacade(): void
    {
        Sms::shouldReceive('sendSms')
            ->once()
            ->with('1234567890', 'Test message')
            ->andReturn(false);

        Sms::sendSms('1234567890', 'Test message');
    }

    public function testSendsBulkSmsFacadeSuccessfully(): void
    {
        Sms::shouldReceive('sendBulkSms')
            ->once()
            ->with(['1234567890', '0987654321'], 'Test message')
            ->andReturn(true);

        Sms::sendBulkSms(['1234567890', '0987654321'], 'Test message');
    }

    public function testFailsToSendBulkSmsFacade(): void
    {
        Sms::shouldReceive('sendBulkSms')
            ->once()
            ->with(['1234567890', '0987654321'], 'Test message')
            ->andReturn(false);

        Sms::sendBulkSms(['1234567890', '0987654321'], 'Test message');
    }

    public function testGetsSmsDeliveryStatusFacadeSuccessfully(): void
    {
        Sms::shouldReceive('getSmsDeliveryStatus')
            ->once()
            ->with('messageId')
            ->andReturn('delivered');

        $status = Sms::getSmsDeliveryStatus('messageId');
        $this->assertEquals('delivered', $status);
    }

    public function testFailsToGetSmsDeliveryStatusFacade(): void
    {
        Sms::shouldReceive('getSmsDeliveryStatus')
            ->once()
            ->with('messageId')
            ->andReturn(null);

        $status = Sms::getSmsDeliveryStatus('messageId');
        $this->assertNull($status);
    }
}
