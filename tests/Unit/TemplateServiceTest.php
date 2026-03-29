<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use InvalidArgumentException;
use Moffhub\SmsHandler\Exceptions\InvalidMessageException;
use Moffhub\SmsHandler\Services\TemplateService;
use Moffhub\SmsHandler\Tests\TestCase;

class TemplateServiceTest extends TestCase
{
    protected TemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateService = new TemplateService;
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('sms.templates', [
            'otp' => 'Your verification code is {{ code }}. Valid for {{ minutes }} minutes.',
            'welcome' => ['body' => 'Welcome {{ name }}! Thanks for joining.'],
            'simple' => 'Hello world',
        ]);
    }

    public function test_renders_template_with_variables(): void
    {
        $result = $this->templateService->render('otp', [
            'code' => '1234',
            'minutes' => '5',
        ]);

        $this->assertEquals('Your verification code is 1234. Valid for 5 minutes.', $result);
    }

    public function test_renders_template_with_array_body(): void
    {
        $result = $this->templateService->render('welcome', [
            'name' => 'John',
        ]);

        $this->assertEquals('Welcome John! Thanks for joining.', $result);
    }

    public function test_renders_template_without_variables(): void
    {
        $result = $this->templateService->render('simple');

        $this->assertEquals('Hello world', $result);
    }

    public function test_throws_exception_for_unknown_template(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("SMS template 'nonexistent' not found");

        $this->templateService->render('nonexistent');
    }

    public function test_interpolates_both_brace_formats(): void
    {
        $template = 'Code: {{code}} or {{ code }}';
        $result = $this->templateService->interpolate($template, ['code' => '9999']);

        $this->assertEquals('Code: 9999 or 9999', $result);
    }

    public function test_leaves_unmatched_variables_intact(): void
    {
        $result = $this->templateService->render('otp', ['code' => '1234']);

        $this->assertStringContainsString('1234', $result);
        $this->assertStringContainsString('{{ minutes }}', $result);
    }

    public function test_validates_rendered_message_length(): void
    {
        $this->app['config']->set('sms.max_message_length', 20);
        $this->app['config']->set('sms.templates.long', 'This message has {{ filler }} that is very long');

        $templateService = new TemplateService;

        $this->expectException(InvalidMessageException::class);

        $templateService->render('long', ['filler' => 'a very long filler text that exceeds']);
    }

    public function test_get_template_names(): void
    {
        $names = $this->templateService->getTemplateNames();

        $this->assertContains('otp', $names);
        $this->assertContains('welcome', $names);
        $this->assertContains('simple', $names);
    }

    public function test_exists_returns_true_for_known_template(): void
    {
        $this->assertTrue($this->templateService->exists('otp'));
    }

    public function test_exists_returns_false_for_unknown_template(): void
    {
        $this->assertFalse($this->templateService->exists('nonexistent'));
    }
}
