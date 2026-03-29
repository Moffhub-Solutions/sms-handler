<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Moffhub\SmsHandler\Tests\TestCase;

class WebhookSignatureValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('sms.webhooks.enabled', true);
        $app['config']->set('sms.webhooks.secrets.twilio', 'twilio_test_secret');
        $app['config']->set('sms.webhooks.secrets.africastalking', 'at_test_token');
        $app['config']->set('sms.webhooks.secrets.advanta', 'advanta_test_secret');
        $app['config']->set('sms.webhooks.secrets.nexmo', 'nexmo_test_secret');
        $app['config']->set('sms.webhooks.secrets.onfon', 'onfon_test_key');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../src/Database/Migrations');
    }

    public function test_twilio_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson('/sms/webhooks/twilio', [
            'MessageSid' => 'SM123',
            'MessageStatus' => 'delivered',
            'To' => '+254712345678',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid webhook signature']);
    }

    public function test_twilio_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/sms/webhooks/twilio', [
            'MessageSid' => 'SM123',
            'MessageStatus' => 'delivered',
            'To' => '+254712345678',
        ], [
            'X-Twilio-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(403);
    }

    public function test_africastalking_webhook_rejects_invalid_token(): void
    {
        $response = $this->postJson('/sms/webhooks/africastalking', [
            'id' => 'ATXid_123',
            'status' => 'Success',
            'phoneNumber' => '+254712345678',
        ], [
            'X-AT-Signature' => 'wrong_token',
        ]);

        $response->assertStatus(403);
    }

    public function test_africastalking_webhook_accepts_valid_token(): void
    {
        $response = $this->postJson('/sms/webhooks/africastalking', [
            'id' => 'ATXid_123',
            'status' => 'Success',
            'phoneNumber' => '+254712345678',
        ], [
            'X-AT-Signature' => 'at_test_token',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_advanta_webhook_rejects_missing_secret(): void
    {
        $response = $this->postJson('/sms/webhooks/advanta', [
            'messageId' => 'msg_123',
            'status' => 'DeliveredToTerminal',
            'phoneNumber' => '+254712345678',
        ]);

        $response->assertStatus(403);
    }

    public function test_advanta_webhook_accepts_valid_secret(): void
    {
        $response = $this->postJson('/sms/webhooks/advanta', [
            'messageId' => 'msg_123',
            'status' => 'DeliveredToTerminal',
            'phoneNumber' => '+254712345678',
        ], [
            'X-Webhook-Secret' => 'advanta_test_secret',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_nexmo_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/sms/webhooks/nexmo', [
            'messageId' => 'msg_123',
            'status' => 'delivered',
            'to' => '+254712345678',
        ], [
            'X-Vonage-Signature' => 'invalid_sig',
        ]);

        $response->assertStatus(403);
    }

    public function test_nexmo_webhook_accepts_valid_hmac_signature(): void
    {
        $payload = json_encode([
            'messageId' => 'msg_123',
            'status' => 'delivered',
            'to' => '+254712345678',
        ]);

        $signature = hash_hmac('sha256', $payload, 'nexmo_test_secret');

        $response = $this->call('POST', '/sms/webhooks/nexmo', [], [], [], [
            'HTTP_X-Vonage-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
    }

    public function test_onfon_webhook_rejects_missing_api_key(): void
    {
        $response = $this->postJson('/sms/webhooks/onfon', [
            'MessageId' => 'msg_123',
            'Status' => 'delivered',
            'Number' => '+254712345678',
        ]);

        $response->assertStatus(403);
    }

    public function test_onfon_webhook_accepts_valid_api_key(): void
    {
        $response = $this->postJson('/sms/webhooks/onfon', [
            'MessageId' => 'msg_123',
            'Status' => 'delivered',
            'Number' => '+254712345678',
        ], [
            'X-API-Key' => 'onfon_test_key',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_webhook_allows_request_when_no_secret_configured(): void
    {
        // Clear the secret for advanta
        $this->app['config']->set('sms.webhooks.secrets.advanta', null);

        $response = $this->postJson('/sms/webhooks/advanta', [
            'messageId' => 'msg_123',
            'status' => 'DeliveredToTerminal',
            'phoneNumber' => '+254712345678',
        ]);

        // Should pass through since no secret means validation is skipped
        $response->assertStatus(200);
    }
}
