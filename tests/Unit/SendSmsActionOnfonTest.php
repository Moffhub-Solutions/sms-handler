<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Moffhub\SmsHandler\Actions\Onfon\SendSmsAction;
use Moffhub\SmsHandler\Exceptions\ProviderException;
use Moffhub\SmsHandler\Tests\TestCase;

class SendSmsActionOnfonTest extends TestCase
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
                'Data' => [
                    [
                        'MessageId' => 'onfon_msg_123',
                        'MessageErrorCode' => '0',
                        'MobileNumber' => '254712345678',
                        'MessageErrorDescription' => 'Success',
                    ],
                ],
            ]),
        ]);

        $payload = ['ClientId' => 'test_client', 'ApiKey' => 'test_key'];
        $result = $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');

        $this->assertCount(1, $result);
        $this->assertEquals('onfon_msg_123', $result->first()->messageId);
        $this->assertEquals('0', $result->first()->status);
        $this->assertEquals('254712345678', $result->first()->to);
        $this->assertEquals('onfon', $result->first()->provider);
    }

    public function test_parses_multiple_responses(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [
                    [
                        'MessageId' => 'onfon_1',
                        'MessageErrorCode' => '0',
                        'MobileNumber' => '254712345678',
                    ],
                    [
                        'MessageId' => 'onfon_2',
                        'MessageErrorCode' => '0',
                        'MobileNumber' => '254712345679',
                    ],
                ],
            ]),
        ]);

        $payload = ['ClientId' => 'test_client'];
        $result = $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');

        $this->assertCount(2, $result);
    }

    public function test_returns_empty_collection_for_empty_data(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [],
            ]),
        ]);

        $payload = ['ClientId' => 'test_client'];
        $result = $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');

        $this->assertTrue($result->isEmpty());
    }

    public function test_throws_on_http_failure(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('failed to send');

        $payload = ['ClientId' => 'test_client'];
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');
    }

    public function test_throws_on_missing_data_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'results' => [],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response format');

        $payload = ['ClientId' => 'test_client'];
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');
    }

    public function test_throws_on_null_json_response(): void
    {
        Http::fake([
            '*' => Http::response('not json', 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->expectException(ProviderException::class);

        $payload = ['ClientId' => 'test_client'];
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');
    }

    public function test_throws_on_empty_response_body(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $this->expectException(ProviderException::class);

        $payload = ['ClientId' => 'test_client'];
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');
    }

    public function test_handles_response_with_missing_item_fields(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [
                    [
                        'MessageErrorCode' => '0',
                        // Missing MessageId, MobileNumber, MessageErrorDescription
                    ],
                ],
            ]),
        ]);

        $payload = ['ClientId' => 'test_client'];
        $result = $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');

        $this->assertCount(1, $result);
        $this->assertEquals('', $result->first()->messageId);
        $this->assertEquals('', $result->first()->to);
    }

    public function test_throws_on_non_array_data_value(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => 'not an array',
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response format');

        $payload = ['ClientId' => 'test_client'];
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');
    }

    public function test_throws_on_non_array_item_in_data(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => ['not_an_array_item'],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response format');

        $payload = ['ClientId' => 'test_client'];
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');
    }

    public function test_sends_access_key_header(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [],
            ]),
        ]);

        $payload = ['ClientId' => 'my_client_id'];
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');

        Http::assertSent(function ($request) {
            return $request->hasHeader('AccessKey', 'my_client_id');
        });
    }

    public function test_handles_missing_client_id_in_payload(): void
    {
        Http::fake([
            '*' => Http::response([
                'Data' => [],
            ]),
        ]);

        $payload = ['ApiKey' => 'test_key']; // No ClientId
        $this->action->execute('https://api.onfon.test/send', $payload, 'Hello');

        Http::assertSent(function ($request) {
            return $request->hasHeader('AccessKey', '');
        });
    }
}
