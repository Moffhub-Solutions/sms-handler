<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Moffhub\SmsHandler\Models\SmsLog;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsLogScopesTest extends TestCase
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
            $table->float('estimated_cost')->nullable();
            $table->integer('segment_count')->nullable();
            $table->timestamps();
        });
    }

    public function test_scope_for_provider(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true]);
        SmsLog::create(['provider' => 'twilio', 'to' => '254712345678', 'message' => 'B', 'success' => true]);

        $results = SmsLog::forProvider('advanta')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('advanta', $results->first()->provider);
    }

    public function test_scope_for_recipient(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true]);
        SmsLog::create(['provider' => 'advanta', 'to' => '254700000000', 'message' => 'B', 'success' => true]);

        $results = SmsLog::forRecipient('254712345678')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('254712345678', $results->first()->to);
    }

    public function test_scope_failed(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => false]);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'B', 'success' => true]);

        $results = SmsLog::failed()->get();

        $this->assertCount(1, $results);
        $this->assertFalse($results->first()->success);
    }

    public function test_scope_delivered(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true, 'delivery_status' => 'delivered']);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'B', 'success' => true, 'delivery_status' => 'DeliveredToTerminal']);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'C', 'success' => true, 'delivery_status' => 'pending']);

        $results = SmsLog::delivered()->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_pending(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true, 'delivery_status' => 'pending']);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'B', 'success' => true, 'delivery_status' => null]);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'C', 'success' => true, 'delivery_status' => 'delivered']);

        $results = SmsLog::pending()->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_sent(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true]);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'B', 'success' => false]);

        $results = SmsLog::sent()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->success);
    }

    public function test_scope_scheduled(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true, 'scheduled_at' => now()->addHour()]);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'B', 'success' => true, 'scheduled_at' => now()->subHour()]);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'C', 'success' => true, 'scheduled_at' => null]);

        $results = SmsLog::scheduled()->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_between(): void
    {
        Carbon::setTestNow('2026-03-15 12:00:00');

        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true]);

        Carbon::setTestNow('2026-03-10 12:00:00');

        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'B', 'success' => true]);

        Carbon::setTestNow('2026-03-18 12:00:00');

        $results = SmsLog::between('2026-03-14', '2026-03-16')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('A', $results->first()->message);

        Carbon::setTestNow();
    }

    public function test_scope_recent(): void
    {
        Carbon::setTestNow('2026-03-18 12:00:00');

        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'Recent', 'success' => true]);

        Carbon::setTestNow('2026-03-16 12:00:00');

        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'Old', 'success' => true]);

        Carbon::setTestNow('2026-03-18 12:00:00');

        $results = SmsLog::recent(24)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Recent', $results->first()->message);

        Carbon::setTestNow();
    }

    public function test_scopes_can_be_chained(): void
    {
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'A', 'success' => true, 'delivery_status' => 'delivered']);
        SmsLog::create(['provider' => 'twilio', 'to' => '254712345678', 'message' => 'B', 'success' => true, 'delivery_status' => 'delivered']);
        SmsLog::create(['provider' => 'advanta', 'to' => '254712345678', 'message' => 'C', 'success' => false]);

        $results = SmsLog::forProvider('advanta')->sent()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('A', $results->first()->message);
    }
}
