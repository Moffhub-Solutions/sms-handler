<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Actions\Advanta\SendSmsAction;
use Moffhub\SmsHandler\Exceptions\ProviderException;
use Moffhub\SmsHandler\Tests\TestCase;

class SendSmsActionAdvantaTest extends TestCase
{
    protected SendSmsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SendSmsAction;
    }

    public function test_parses_successful_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'response-description' => 'Success',
                        'mobile' => '254712345678',
                        'messageid' => 'msg_123',
                        'networkid' => 'net_456',
                    ],
                ],
            ]),
        ]);

        $result = $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');

        $this->assertCount(1, $result);
        $this->assertEquals('msg_123', $result->first()->messageId);
        $this->assertEquals('200', $result->first()->status);
        $this->assertEquals('254712345678', $result->first()->to);
        $this->assertEquals('advanta', $result->first()->provider);
    }

    public function test_parses_multiple_responses(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        'mobile' => '254712345678',
                        'messageid' => 'msg_1',
                    ],
                    [
                        'response-code' => 200,
                        'mobile' => '254712345679',
                        'messageid' => 'msg_2',
                    ],
                ],
            ]),
        ]);

        $result = $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');

        $this->assertCount(2, $result);
    }

    public function test_returns_empty_collection_for_empty_responses(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [],
            ]),
        ]);

        $result = $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');

        $this->assertTrue($result->isEmpty());
    }

    public function test_throws_on_http_failure(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('failed to send');

        $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');
    }

    public function test_throws_on_missing_responses_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'data' => [],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response format');

        $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');
    }

    public function test_throws_on_null_json_response(): void
    {
        Http::fake([
            '*' => Http::response('not json at all', 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->expectException(ProviderException::class);

        $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');
    }

    public function test_throws_on_empty_response_body(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $this->expectException(ProviderException::class);

        $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');
    }

    public function test_handles_response_with_missing_item_fields(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => [
                    [
                        'response-code' => 200,
                        // Missing messageid, mobile, networkid, response-description
                    ],
                ],
            ]),
        ]);

        $result = $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');

        $this->assertCount(1, $result);
        $this->assertEquals('', $result->first()->messageId);
        $this->assertEquals('', $result->first()->to);
    }

    public function test_throws_on_non_array_responses_value(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => 'not an array',
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response format');

        $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');
    }

    public function test_throws_on_non_array_item_in_responses(): void
    {
        Http::fake([
            '*' => Http::response([
                'responses' => ['not_an_array_item'],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response format');

        $this->action->execute('https://api.test/send', ['key' => 'val'], 'Hello');
    }
}
