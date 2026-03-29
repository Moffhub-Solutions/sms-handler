<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Moffhub\SmsHandler\Events\InboundSmsReceived;
use Moffhub\SmsHandler\Tests\TestCase;

class InboundSmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('sms.inbound.enabled', true);
        $app['config']->set('sms.inbound.route_prefix', 'sms/inbound');
        $app['config']->set('sms.webhooks.enabled', true);
    }

    public function test_advanta_inbound_returns_200_and_dispatches_event(): void
    {
        $response = $this->postJson('/sms/inbound/advanta', [
            'from' => '+254712345678',
            'message' => 'Hello from Advanta',
            'messageId' => 'adv_inbound_123',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) {
            return $event->phone === '+254712345678'
                && $event->message === 'Hello from Advanta'
                && $event->provider === 'advanta'
                && $event->providerMessageId === 'adv_inbound_123';
        });
    }

    public function test_africastalking_inbound_returns_200_and_dispatches_event(): void
    {
        $response = $this->postJson('/sms/inbound/africastalking', [
            'from' => '+254712345678',
            'text' => 'Hello from AT',
            'id' => 'at_inbound_456',
            'date' => '2024-01-01 12:00:00',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) {
            return $event->phone === '+254712345678'
                && $event->message === 'Hello from AT'
                && $event->provider === 'africastalking'
                && $event->providerMessageId === 'at_inbound_456';
        });
    }

    public function test_onfon_inbound_returns_200_and_dispatches_event(): void
    {
        $response = $this->postJson('/sms/inbound/onfon', [
            'From' => '+254712345678',
            'Message' => 'Hello from Onfon',
            'MessageId' => 'onfon_inbound_789',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) {
            return $event->phone === '+254712345678'
                && $event->message === 'Hello from Onfon'
                && $event->provider === 'onfon'
                && $event->providerMessageId === 'onfon_inbound_789';
        });
    }

    public function test_nexmo_inbound_returns_200_and_dispatches_event(): void
    {
        $response = $this->postJson('/sms/inbound/nexmo', [
            'msisdn' => '+254712345678',
            'text' => 'Hello from Nexmo',
            'messageId' => 'nexmo_inbound_101',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) {
            return $event->phone === '+254712345678'
                && $event->message === 'Hello from Nexmo'
                && $event->provider === 'nexmo'
                && $event->providerMessageId === 'nexmo_inbound_101';
        });
    }

    public function test_twilio_inbound_returns_200_and_dispatches_event(): void
    {
        $response = $this->postJson('/sms/inbound/twilio', [
            'From' => '+254712345678',
            'Body' => 'Hello from Twilio',
            'MessageSid' => 'SM_inbound_202',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) {
            return $event->phone === '+254712345678'
                && $event->message === 'Hello from Twilio'
                && $event->provider === 'twilio'
                && $event->providerMessageId === 'SM_inbound_202';
        });
    }

    public function test_inbound_event_contains_raw_payload(): void
    {
        $payload = [
            'from' => '+254712345678',
            'message' => 'Test payload',
            'messageId' => 'msg_raw_test',
            'extra_field' => 'extra_value',
        ];

        $this->postJson('/sms/inbound/advanta', $payload);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) use ($payload) {
            return $event->rawPayload === $payload;
        });
    }

    public function test_inbound_event_has_received_at_timestamp(): void
    {
        $this->postJson('/sms/inbound/advanta', [
            'from' => '+254712345678',
            'message' => 'Timestamp test',
            'messageId' => 'msg_ts_test',
        ]);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) {
            return $event->receivedAt !== null
                && $event->receivedAt->isToday();
        });
    }

    public function test_inbound_with_empty_payload_still_returns_200(): void
    {
        $response = $this->postJson('/sms/inbound/advanta', []);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        Event::assertDispatched(InboundSmsReceived::class, function ($event) {
            return $event->phone === ''
                && $event->message === '';
        });
    }
}
