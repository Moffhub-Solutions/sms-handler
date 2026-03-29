<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Exceptions\InvalidPhoneNumberException;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;

class PhoneNumberValidationTest extends TestCase
{
    protected SmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsService = new SmsService($this->app->make(SmsManager::class));
    }

    public function test_rejects_empty_phone_number(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Phone number cannot be empty');

        $this->smsService->sendSms('', 'Hello');
    }

    public function test_rejects_whitespace_only_phone_number(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Phone number cannot be empty');

        $this->smsService->sendSms('   ', 'Hello');
    }

    public function test_rejects_too_short_phone_number(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Invalid phone number');

        $this->smsService->sendSms('12345', 'Hello');
    }

    public function test_rejects_too_long_phone_number(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Invalid phone number');

        $this->smsService->sendSms('1234567890123456', 'Hello');
    }

    public function test_accepts_valid_kenyan_phone_number(): void
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

        $result = $this->smsService->sendSms('0712345678', 'Hello');

        Http::assertSentCount(1);
    }

    public function test_accepts_valid_international_phone_number(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '+254712345678',
                        'messageid' => 'msg123',
                        'networkid' => 'net456',
                    ],
                ],
            ]),
        ]);

        $result = $this->smsService->sendSms('+254712345678', 'Hello');

        Http::assertSentCount(1);
    }

    public function test_bulk_sms_rejects_empty_phone_in_recipients(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Phone number cannot be empty');

        $this->smsService->sendBulkSms(['0712345678', '', '0712345680'], 'Hello');
    }

    public function test_bulk_sms_rejects_invalid_phone_in_recipients(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Invalid phone number');

        $this->smsService->sendBulkSms(['0712345678', '123', '0712345680'], 'Hello');
    }

    public function test_bulk_sms_accepts_all_valid_numbers(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    ['response-code' => 200, 'mobile' => '254712345678', 'messageid' => 'msg1'],
                    ['response-code' => 200, 'mobile' => '254712345679', 'messageid' => 'msg2'],
                ],
            ]),
        ]);

        $result = $this->smsService->sendBulkSms(['0712345678', '0712345679'], 'Hello');

        Http::assertSentCount(1);
    }

    public function test_rejects_non_numeric_phone_number(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Invalid phone number');

        $this->smsService->sendSms('abcdefghij', 'Hello');
    }
}
