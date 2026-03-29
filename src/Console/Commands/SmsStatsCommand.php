<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Console\Commands;

use Illuminate\Console\Command;
use Moffhub\SmsHandler\Services\SmsAnalytics;

class SmsStatsCommand extends Command
{
    protected $signature = 'sms:stats
                            {--provider= : Filter by provider name}
                            {--days=30 : Number of days to look back}';

    protected $description = 'Display SMS sending statistics overview';

    public function handle(SmsAnalytics $analytics): int
    {
        $days = (int) $this->option('days');
        $provider = $this->option('provider');

        $this->info("SMS Statistics (last {$days} days)");
        $this->newLine();

        $query = $analytics->lastDays($days);

        if ($provider) {
            $query = $query->forProvider($provider);
        }

        // Overall summary
        $summary = $query->summary();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Sent', (string) $summary['total_sent']],
                ['Delivered', (string) $summary['total_delivered']],
                ['Failed', (string) $summary['total_failed']],
                ['Success Rate', $summary['success_rate'].'%'],
                ['Provider', $summary['provider'] ?? 'All'],
                ['Period', ($summary['period']['from'] ?? 'N/A').' to '.($summary['period']['to'] ?? 'N/A')],
            ]
        );

        // Per-provider breakdown (only if no specific provider filter)
        if (! $provider) {
            $this->newLine();
            $this->info('Per-Provider Breakdown');

            $providerStats = $analytics->lastDays($days)->perProviderSummary();

            if ($providerStats->isEmpty()) {
                $this->warn('No SMS data found for the given period.');
            } else {
                $this->table(
                    ['Provider', 'Sent', 'Delivered', 'Failed', 'Success Rate'],
                    $providerStats->map(fn (array $row) => [
                        $row['provider'],
                        (string) $row['sent'],
                        (string) $row['delivered'],
                        (string) $row['failed'],
                        $row['success_rate'].'%',
                    ])->toArray()
                );
            }
        }

        return self::SUCCESS;
    }
}
