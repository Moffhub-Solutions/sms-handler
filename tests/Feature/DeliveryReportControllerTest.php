<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Moffhub\SmsHandler\Events\DeliveryReportReceived;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\Tests\TestCase;

class DeliveryReportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        // Create the table directly instead of running migrations that have SQLite incompatibilities
        Schema::create('sms_logs', function ($table) {
            $table->id();
            $table->string('ulid')->nullable();
            $table->string('message_id')->nullable();
            $table->string('provider');
            $table->string('to');
            $table->text('message');
            $table->boolean('success')->default(false);
            $table->string('delivery_status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('sms.webhooks.enabled', true);
        $app['config']->set('sms.webhooks.prefix', 'sms/webhooks');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_advanta_webhook_processes_delivery_report(): void
    {
        SmsLog::create([
            'provider' => 'advanta',
            'to' => '+254712345678',
            'message' => 'Test message',
            'success' => true,
            'message_id' => 'msg_123',
        ]);

        $response = $this->postJson('/sms/webhooks/advanta', [
            'messageId' => 'msg_123',
            'status' => 'DeliveredToTerminal',
            'phoneNumber' => '+254712345678',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        Event::assertDispatched(DeliveryReportReceived::class, function ($event) {
            return $event->provider === 'advanta'
                && $event->messageId === 'msg_123'
                && $event->status === 'DeliveredToTerminal';
        });
    }

    public function test_africastalking_webhook_processes_delivery_report(): void
    {
        $response = $this->postJson('/sms/webhooks/africastalking', [
            'id' => 'ATXid_123',
            'status' => 'Success',
            'phoneNumber' => '+254712345678',
        ]);

        $response->assertOk();

        Event::assertDispatched(DeliveryReportReceived::class, function ($event) {
            return $event->provider === 'africastalking'
                && $event->messageId === 'ATXid_123';
        });
    }

    public function test_onfon_webhook_processes_delivery_report(): void
    {
        $response = $this->postJson('/sms/webhooks/onfon', [
            'MessageId' => 'onfon_123',
            'Status' => 'Delivered',
            'Number' => '+254712345678',
        ]);

        $response->assertOk();

        Event::assertDispatched(DeliveryReportReceived::class, function ($event) {
            return $event->provider === 'onfon'
                && $event->messageId === 'onfon_123';
        });
    }

    public function test_nexmo_webhook_processes_delivery_report(): void
    {
        $response = $this->postJson('/sms/webhooks/nexmo', [
            'messageId' => 'nexmo_msg_123',
            'status' => 'delivered',
            'to' => '+254712345678',
        ]);

        $response->assertOk();

        Event::assertDispatched(DeliveryReportReceived::class, function ($event) {
            return $event->provider === 'nexmo'
                && $event->messageId === 'nexmo_msg_123';
        });
    }

    public function test_twilio_webhook_processes_delivery_report(): void
    {
        $response = $this->postJson('/sms/webhooks/twilio', [
            'MessageSid' => 'SM_test_123',
            'MessageStatus' => 'delivered',
            'To' => '+254712345678',
        ]);

        $response->assertOk();

        Event::assertDispatched(DeliveryReportReceived::class, function ($event) {
            return $event->provider === 'twilio'
                && $event->messageId === 'SM_test_123';
        });
    }

    public function test_webhook_handles_missing_message_id(): void
    {
        $response = $this->postJson('/sms/webhooks/advanta', [
            'status' => 'DeliveredToTerminal',
            'phoneNumber' => '+254712345678',
        ]);

        $response->assertOk();

        // Event should not be dispatched when messageId is missing
        Event::assertNotDispatched(DeliveryReportReceived::class);
    }

    public function test_advanta_webhook_updates_sms_log(): void
    {
        $smsLog = SmsLog::create([
            'provider' => 'advanta',
            'to' => '+254712345678',
            'message' => 'Test',
            'success' => true,
            'message_id' => 'msg_update_test',
        ]);

        $this->postJson('/sms/webhooks/advanta', [
            'messageId' => 'msg_update_test',
            'status' => 'DeliveredToTerminal',
            'phoneNumber' => '+254712345678',
        ]);

        $smsLog->refresh();
        $this->assertEquals('DeliveredToTerminal', $smsLog->delivery_status);
    }

    public function test_webhook_with_empty_payload(): void
    {
        $response = $this->postJson('/sms/webhooks/advanta', []);

        $response->assertOk();
        Event::assertNotDispatched(DeliveryReportReceived::class);
    }
}
