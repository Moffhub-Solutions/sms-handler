<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Console\Commands;

use Illuminate\Console\Command;
use Moffhub\SmsHandler\Services\SmsService;
use Throwable;

class SmsHealthCheckCommand extends Command
{
    protected $signature = 'sms:health
                            {--provider= : Check a specific provider only}';

    protected $description = 'Check SMS provider connectivity and configuration status';

    public function handle(SmsService $smsService): int
    {
        $specificProvider = $this->option('provider');
        $providers = $specificProvider
            ? [$specificProvider]
            : $smsService->getAvailableProviders();

        $this->info('SMS Provider Health Check');
        $this->newLine();

        $rows = [];
        $hasFailures = false;

        foreach ($providers as $provider) {
            $configured = $smsService->isProviderConfigured($provider);

            if (! $configured) {
                $rows[] = [$provider, 'NOT_CONFIGURED', 'N/A'];
                $hasFailures = true;

                continue;
            }

            // Try to check balance as a connectivity test
            try {
                config(['sms.default' => $provider]);
                $balance = $smsService->getSmsBalance();
                $rows[] = [$provider, 'OK', (string) $balance];
            } catch (Throwable $e) {
                $rows[] = [$provider, 'FAIL', $e->getMessage()];
                $hasFailures = true;
            }
        }

        $this->table(['Provider', 'Status', 'Balance / Details'], $rows);

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
