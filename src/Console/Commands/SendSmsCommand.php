<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Console\Commands;

use Illuminate\Console\Command;
use Moffhub\SmsHandler\Services\SmsService;
use Throwable;

class SendSmsCommand extends Command
{
    protected $signature = 'sms:send
                            {phone : The recipient phone number}
                            {message : The SMS message to send}
                            {--provider= : Override the default provider}
                            {--dry-run : Validate without sending}';

    protected $description = 'Send an SMS message to a phone number';

    public function handle(SmsService $smsService): int
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message');
        $provider = $this->option('provider');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run mode — validating inputs only.');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Phone', $phone],
                    ['Message', $message],
                    ['Provider', $provider ?: $smsService->getDefaultProvider()],
                ]
            );
            $this->info('Validation passed. No SMS was sent.');

            return self::SUCCESS;
        }

        $this->info("Sending SMS to {$phone}...");

        try {
            if ($provider) {
                config(['sms.default' => $provider]);
            }

            $responses = $smsService->sendSms($phone, $message);

            if ($responses && $responses->isNotEmpty()) {
                $response = $responses->first();
                $this->info('SMS sent successfully.');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Message ID', $response->messageId ?: 'N/A'],
                        ['Status', $response->status],
                        ['Provider', $response->provider],
                        ['To', $response->to],
                    ]
                );

                return self::SUCCESS;
            }

            $this->error('SMS send returned no response.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Failed to send SMS: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
