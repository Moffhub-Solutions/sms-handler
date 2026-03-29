<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsLogTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_can_create_sms_log(): void
    {
        $log = SmsLog::create([
            'provider' => 'advanta',
            'to' => '+254712345678',
            'message' => 'Test message',
            'success' => true,
            'message_id' => 'msg_123',
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'provider' => 'advanta',
            'to' => '+254712345678',
            'message' => 'Test message',
            'message_id' => 'msg_123',
        ]);

        $this->assertEquals('advanta', $log->provider);
        $this->assertEquals('+254712345678', $log->to);
        $this->assertTrue($log->success);
    }

    public function test_casts_success_to_boolean(): void
    {
        $log = SmsLog::create([
            'provider' => 'twilio',
            'to' => '+254712345678',
            'message' => 'Test',
            'success' => 1,
        ]);

        $log->refresh();
        $this->assertIsBool($log->success);
        $this->assertTrue($log->success);
    }

    public function test_casts_response_to_array(): void
    {
        $responseData = ['sid' => 'SM_123', 'status' => 'queued'];

        $log = SmsLog::create([
            'provider' => 'twilio',
            'to' => '+254712345678',
            'message' => 'Test',
            'success' => true,
            'response' => $responseData,
        ]);

        $log->refresh();
        $this->assertIsArray($log->response);
        $this->assertEquals('SM_123', $log->response['sid']);
    }

    public function test_casts_scheduled_at_to_immutable_datetime(): void
    {
        $scheduledAt = CarbonImmutable::now()->addHour();

        $log = SmsLog::create([
            'provider' => 'advanta',
            'to' => '+254712345678',
            'message' => 'Test',
            'success' => true,
            'scheduled_at' => $scheduledAt,
        ]);

        $log->refresh();
        $this->assertInstanceOf(CarbonImmutable::class, $log->scheduled_at);
    }

    public function test_fillable_attributes(): void
    {
        $log = new SmsLog;

        $this->assertContains('provider', $log->getFillable());
        $this->assertContains('to', $log->getFillable());
        $this->assertContains('message', $log->getFillable());
        $this->assertContains('success', $log->getFillable());
        $this->assertContains('message_id', $log->getFillable());
        $this->assertContains('delivery_status', $log->getFillable());
        $this->assertContains('response', $log->getFillable());
        $this->assertContains('scheduled_at', $log->getFillable());
    }

    public function test_nullable_fields(): void
    {
        $log = SmsLog::create([
            'provider' => 'nexmo',
            'to' => '+254712345678',
            'message' => 'Test',
            'success' => false,
        ]);

        $log->refresh();
        $this->assertNull($log->message_id);
        $this->assertNull($log->delivery_status);
        $this->assertNull($log->response);
        $this->assertNull($log->scheduled_at);
    }

    public function test_can_update_delivery_status(): void
    {
        $log = SmsLog::create([
            'provider' => 'advanta',
            'to' => '+254712345678',
            'message' => 'Test',
            'success' => true,
            'message_id' => 'msg_456',
        ]);

        $log->update(['delivery_status' => 'delivered']);

        $log->refresh();
        $this->assertEquals('delivered', $log->delivery_status);
    }

    public function test_can_store_failed_sms(): void
    {
        $log = SmsLog::create([
            'provider' => 'twilio',
            'to' => '+254712345678',
            'message' => 'Failed message',
            'success' => false,
            'response' => ['error' => 'Invalid phone number'],
        ]);

        $log->refresh();
        $this->assertFalse($log->success);
        $this->assertEquals('Invalid phone number', $log->response['error']);
    }
}
