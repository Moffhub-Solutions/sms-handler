<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Exceptions\InvalidMessageException;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;

class MessageValidationTest extends TestCase
{
    protected SmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsService = new SmsService($this->app->make(SmsManager::class));
    }

    public function test_rejects_empty_message(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('SMS message cannot be empty');

        $this->smsService->sendSms('0712345678', '');
    }

    public function test_rejects_whitespace_only_message(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('SMS message cannot be empty');

        $this->smsService->sendSms('0712345678', '   ');
    }

    public function test_rejects_over_length_message(): void
    {
        $this->expectException(InvalidMessageException::class);

        // Default max is 918 characters
        $longMessage = str_repeat('A', 920);
        $this->smsService->sendSms('0712345678', $longMessage);
    }

    public function test_accepts_valid_message(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '254712345678',
                        'messageid' => 'msg123',
                        'networkid' => 'net456',
                    ],
                ],
            ]),
        ]);

        $this->smsService->sendSms('0712345678', 'Hello World');

        Http::assertSentCount(1);
    }

    public function test_accepts_message_at_max_length(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '254712345678',
                        'messageid' => 'msg123',
                        'networkid' => 'net456',
                    ],
                ],
            ]),
        ]);

        $message = str_repeat('A', 918);
        $this->smsService->sendSms('0712345678', $message);

        Http::assertSentCount(1);
    }

    public function test_respects_custom_max_message_length_config(): void
    {
        $this->app['config']->set('sms.max_message_length', 100);
        // Re-create service so config is fresh
        $this->smsService = new SmsService($this->app->make(SmsManager::class));

        $this->expectException(InvalidMessageException::class);

        $this->smsService->sendSms('0712345678', str_repeat('A', 101));
    }

    public function test_bulk_sms_rejects_empty_message(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('SMS message cannot be empty');

        $this->smsService->sendBulkSms(['0712345678'], '');
    }

    public function test_bulk_sms_rejects_over_length_message(): void
    {
        $this->expectException(InvalidMessageException::class);

        $longMessage = str_repeat('A', 920);
        $this->smsService->sendBulkSms(['0712345678'], $longMessage);
    }
}
