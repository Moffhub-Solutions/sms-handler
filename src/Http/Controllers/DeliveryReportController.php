<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Moffhub\SmsHandler\Events\DeliveryReportReceived;
use Moffhub\SmsHandler\Models\SmsLog;

class DeliveryReportController extends Controller
{
    /**
     * Handle Advanta delivery reports.
     *
     * Expected payload:
     * - messageId: The message ID
     * - status: Delivery status (e.g., "DeliveredToTerminal", "SentToNetwork")
     * - phoneNumber: The recipient phone number
     */
    public function advanta(Request $request): JsonResponse
    {
        $messageId = $request->input('messageId') ?? $request->input('messageid');
        $status = $request->input('status') ?? $request->input('delivery_status');
        $phoneNumber = $request->input('phoneNumber') ?? $request->input('mobile');

        $this->updateDeliveryStatus('advanta', $messageId, $status, $phoneNumber, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Africa's Talking delivery reports.
     *
     * Expected payload:
     * - id: The message ID
     * - status: Delivery status (e.g., "Success", "Failed")
     * - phoneNumber: The recipient phone number
     * - failureReason: Reason for failure (if any)
     */
    public function africastalking(Request $request): JsonResponse
    {
        $messageId = $request->input('id');
        $status = $request->input('status');
        $phoneNumber = $request->input('phoneNumber');

        $this->updateDeliveryStatus('africastalking', $messageId, $status, $phoneNumber, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Onfon Media delivery reports.
     *
     * Expected payload varies - check Onfon documentation
     */
    public function onfon(Request $request): JsonResponse
    {
        $messageId = $request->input('MessageId') ?? $request->input('messageId');
        $status = $request->input('Status') ?? $request->input('status');
        $phoneNumber = $request->input('Number') ?? $request->input('phoneNumber');

        $this->updateDeliveryStatus('onfon', $messageId, $status, $phoneNumber, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Nexmo/Vonage delivery reports.
     *
     * Expected payload:
     * - messageId: The message ID
     * - status: Delivery status (e.g., "delivered", "failed")
     * - to: The recipient phone number
     * - err-code: Error code (if any)
     */
    public function nexmo(Request $request): JsonResponse
    {
        $messageId = $request->input('messageId');
        $status = $request->input('status');
        $phoneNumber = $request->input('to');

        $this->updateDeliveryStatus('nexmo', $messageId, $status, $phoneNumber, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Twilio delivery reports (status callbacks).
     *
     * Expected payload:
     * - MessageSid: The message SID
     * - MessageStatus: Delivery status (e.g., "delivered", "failed", "undelivered")
     * - To: The recipient phone number
     * - ErrorCode: Error code (if any)
     */
    public function twilio(Request $request): JsonResponse
    {
        $messageId = $request->input('MessageSid');
        $status = $request->input('MessageStatus');
        $phoneNumber = $request->input('To');

        $this->updateDeliveryStatus('twilio', $messageId, $status, $phoneNumber, $request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Write a structured log entry to the configured SMS log channel.
     */
    protected function smsLog(string $messageKey, array $context = [], string $level = 'info'): void
    {
        $logChannel = config('sms.log.channel');
        $logger = $logChannel ? Log::channel($logChannel) : Log::getFacadeRoot();

        match ($level) {
            'error' => $logger->error($messageKey, $context),
            'debug' => $logger->debug($messageKey, $context),
            'warning' => $logger->warning($messageKey, $context),
            default => $logger->info($messageKey, $context),
        };
    }

    protected function updateDeliveryStatus(
        string $provider,
        ?string $messageId,
        ?string $status,
        ?string $phoneNumber,
        array $payload
    ): void {
        if (! $messageId) {
            logger()->warning("SMS delivery report missing messageId for {$provider}", $payload);

            return;
        }

        // Try to find the SMS log by message_id field first, then fall back to JSON search
        $smsLog = SmsLog::where('provider', $provider)
            ->where(function ($query) use ($messageId, $phoneNumber) {
                $query->where('message_id', $messageId)
                    ->orWhereJsonContains('response->messageId', $messageId)
                    ->orWhereJsonContains('response->sid', $messageId);

                if ($phoneNumber) {
                    $query->orWhere('to', $phoneNumber);
                }
            })
            ->latest()
            ->first();

        if ($smsLog) {
            $response = $smsLog->response ?? [];
            $response['delivery_report'] = $payload;
            $response['delivery_received_at'] = now()->toIso8601String();

            $smsLog->update([
                'delivery_status' => $status,
                'response' => $response,
            ]);
        }

        // Dispatch event for custom handling
        event(new DeliveryReportReceived($provider, $messageId, $status, $phoneNumber, $payload));

        $this->smsLog('sms.delivery_report', [
            'provider' => $provider,
            'message_id' => $messageId,
            'status' => $status,
            'phone_number' => $phoneNumber,
        ]);
    }
}
