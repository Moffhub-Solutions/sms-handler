<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\SmsManager;
use Moffhub\SmsHandler\Tests\TestCase;

class SmsTemplateBuilderTest extends TestCase
{
    protected SmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsService = new SmsService($this->app->make(SmsManager::class));
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('sms.templates', [
            'otp' => 'Your code is {{ code }}.',
            'welcome' => ['body' => 'Hi {{ name }}!'],
        ]);
    }

    public function test_template_returns_builder_with_rendered_message(): void
    {
        $builder = $this->smsService->template('otp', ['code' => '5678']);

        $this->assertEquals('Your code is 5678.', $builder->getMessage());
    }

    public function test_template_builder_sends_sms(): void
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

        $result = $this->smsService
            ->template('otp', ['code' => '1234'])
            ->to('0712345678')
            ->send();

        Http::assertSentCount(1);
    }

    public function test_template_builder_throws_without_recipient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient phone number is required');

        $this->smsService->template('otp', ['code' => '1234'])->send();
    }
}
