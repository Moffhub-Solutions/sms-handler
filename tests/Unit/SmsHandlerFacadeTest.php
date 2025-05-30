<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Moffhub\SmsHandler\Facades\Sms;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsHandlerFacadeTest extends TestCase
{
    public function test_sends_sms_facade_successfully(): void
    {
        Sms::shouldReceive('sendSms')
            ->once()
            ->with('1234567890', 'Test message')
            ->andReturn(true);

        Sms::sendSms('1234567890', 'Test message');
    }

    public function test_fails_to_send_sms_facade(): void
    {
        Sms::shouldReceive('sendSms')
            ->once()
            ->with('1234567890', 'Test message')
            ->andReturn(false);

        Sms::sendSms('1234567890', 'Test message');
    }

    public function test_sends_bulk_sms_facade_successfully(): void
    {
        Sms::shouldReceive('sendBulkSms')
            ->once()
            ->with(['1234567890', '0987654321'], 'Test message')
            ->andReturn(true);

        Sms::sendBulkSms(['1234567890', '0987654321'], 'Test message');
    }

    public function test_fails_to_send_bulk_sms_facade(): void
    {
        Sms::shouldReceive('sendBulkSms')
            ->once()
            ->with(['1234567890', '0987654321'], 'Test message')
            ->andReturn(false);

        Sms::sendBulkSms(['1234567890', '0987654321'], 'Test message');
    }

    public function test_gets_sms_delivery_status_facade_successfully(): void
    {
        Sms::shouldReceive('getSmsDeliveryStatus')
            ->once()
            ->with('messageId')
            ->andReturn('delivered');

        $status = Sms::getSmsDeliveryStatus('messageId');
        $this->assertEquals('delivered', $status);
    }

    public function test_fails_to_get_sms_delivery_status_facade(): void
    {
        Sms::shouldReceive('getSmsDeliveryStatus')
            ->once()
            ->with('messageId')
            ->andReturn('');

        $status = Sms::getSmsDeliveryStatus('messageId');
        $this->assertEquals('', $status);
    }
}
