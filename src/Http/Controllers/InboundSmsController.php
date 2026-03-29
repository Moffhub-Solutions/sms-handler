<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Moffhub\SmsHandler\Events\InboundSmsReceived;

class InboundSmsController extends Controller
{
    /**
     * Handle inbound SMS from Advanta.
     *
     * Expected payload:
     * - from/phoneNumber: Sender phone number
     * - message/text: The message body
     * - messageId: Provider message ID
     */
    public function advanta(Request $request): JsonResponse
    {
        $phone = (string) ($request->input('from') ?? $request->input('phoneNumber') ?? '');
        $message = (string) ($request->input('message') ?? $request->input('text') ?? '');
        $messageId = $request->input('messageId') ?? $request->input('messageid');

        $this->dispatchInboundEvent('advanta', $phone, $message, $messageId, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle inbound SMS from Africa's Talking.
     *
     * Expected payload:
     * - from: Sender phone number
     * - text: The message body
     * - id: Provider message ID
     * - date: Timestamp of the message
     */
    public function africastalking(Request $request): JsonResponse
    {
        $phone = (string) ($request->input('from') ?? '');
        $message = (string) ($request->input('text') ?? '');
        $messageId = $request->input('id');

        $this->dispatchInboundEvent('africastalking', $phone, $message, $messageId, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle inbound SMS from Onfon Media.
     *
     * Expected payload varies - check Onfon documentation.
     */
    public function onfon(Request $request): JsonResponse
    {
        $phone = (string) ($request->input('From') ?? $request->input('from') ?? '');
        $message = (string) ($request->input('Message') ?? $request->input('message') ?? '');
        $messageId = $request->input('MessageId') ?? $request->input('messageId');

        $this->dispatchInboundEvent('onfon', $phone, $message, $messageId, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle inbound SMS from Nexmo/Vonage.
     *
     * Expected payload:
     * - msisdn: Sender phone number
     * - text: The message body
     * - messageId: Provider message ID
     */
    public function nexmo(Request $request): JsonResponse
    {
        $phone = (string) ($request->input('msisdn') ?? $request->input('from') ?? '');
        $message = (string) ($request->input('text') ?? '');
        $messageId = $request->input('messageId');

        $this->dispatchInboundEvent('nexmo', $phone, $message, $messageId, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle inbound SMS from Twilio.
     *
     * Expected payload:
     * - From: Sender phone number
     * - Body: The message body
     * - MessageSid: Provider message SID
     */
    public function twilio(Request $request): JsonResponse
    {
        $phone = (string) ($request->input('From') ?? '');
        $message = (string) ($request->input('Body') ?? '');
        $messageId = $request->input('MessageSid');

        $this->dispatchInboundEvent('twilio', $phone, $message, $messageId, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Dispatch the InboundSmsReceived event.
     */
    protected function dispatchInboundEvent(
        string $provider,
        string $phone,
        string $message,
        ?string $messageId,
        array $rawPayload
    ): void {
        event(new InboundSmsReceived(
            phone: $phone,
            message: $message,
            provider: $provider,
            providerMessageId: $messageId,
            receivedAt: CarbonImmutable::now(),
            rawPayload: $rawPayload,
        ));
    }
}
