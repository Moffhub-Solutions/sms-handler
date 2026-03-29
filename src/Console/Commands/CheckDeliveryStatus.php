<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Console\Commands;

use Illuminate\Console\Command;
use Moffhub\SmsHandler\Services\SmsService;

class CheckDeliveryStatus extends Command
{
    protected $signature = 'sms:check-delivery {message_id : The message ID to check delivery status for}';

    protected $description = 'Check the delivery status of an SMS message by its message ID';

    public function handle(SmsService $smsService): int
    {
        $messageId = $this->argument('message_id');

        $this->info("Checking delivery status for message: {$messageId}");

        try {
            $status = $smsService->getSmsDeliveryStatus($messageId);

            $this->info("Delivery status: {$status}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to check delivery status: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
