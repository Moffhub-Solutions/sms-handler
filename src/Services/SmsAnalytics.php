<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Moffhub\SmsHandler\Models\SmsLog;

class SmsAnalytics
{
    protected ?string $provider = null;

    protected ?Carbon $fromDate = null;

    protected ?Carbon $toDate = null;

    /**
     * Scope analytics to a specific provider.
     */
    public function forProvider(string $provider): static
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    /**
     * Scope analytics to the last N days.
     */
    public function lastDays(int $days): static
    {
        $clone = clone $this;
        $clone->fromDate = Carbon::now()->subDays($days)->startOfDay();
        $clone->toDate = Carbon::now()->endOfDay();

        return $clone;
    }

    /**
     * Alias for lastDays(30).
     */
    public function last30Days(): static
    {
        return $this->lastDays(30);
    }

    /**
     * Alias for lastDays(7).
     */
    public function last7Days(): static
    {
        return $this->lastDays(7);
    }

    /**
     * Scope analytics to a custom date range.
     */
    public function between(Carbon $from, Carbon $to): static
    {
        $clone = clone $this;
        $clone->fromDate = $from;
        $clone->toDate = $to;

        return $clone;
    }

    /**
     * Get aggregated analytics summary.
     *
     * @return array{total_sent: int, total_delivered: int, total_failed: int, success_rate: float, provider: string|null, period: array{from: string|null, to: string|null}}
     */
    public function summary(): array
    {
        $query = SmsLog::query();

        if ($this->provider) {
            $query->where('provider', 'like', "%{$this->provider}%");
        }

        if ($this->fromDate) {
            $query->where('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate) {
            $query->where('created_at', '<=', $this->toDate);
        }

        $totalSent = (clone $query)->count();
        $totalDelivered = (clone $query)->where('success', true)->count();
        $totalFailed = (clone $query)->where('success', false)->count();

        $successRate = $totalSent > 0
            ? round(($totalDelivered / $totalSent) * 100, 2)
            : 0.0;

        return [
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_failed' => $totalFailed,
            'success_rate' => $successRate,
            'provider' => $this->provider,
            'period' => [
                'from' => $this->fromDate?->toDateTimeString(),
                'to' => $this->toDate?->toDateTimeString(),
            ],
        ];
    }

    /**
     * Get daily breakdown of SMS analytics.
     *
     * @return Collection<int, array{date: string, sent: int, delivered: int, failed: int}>
     */
    public function dailyBreakdown(): Collection
    {
        $query = SmsLog::query()
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as sent')
            ->selectRaw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as delivered')
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            ->groupBy('date')
            ->orderBy('date', 'desc');

        if ($this->provider) {
            $query->where('provider', 'like', "%{$this->provider}%");
        }

        if ($this->fromDate) {
            $query->where('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate) {
            $query->where('created_at', '<=', $this->toDate);
        }

        return $query->get()->map(fn ($row) => [
            'date' => $row->date,
            'sent' => (int) $row->sent,
            'delivered' => (int) $row->delivered,
            'failed' => (int) $row->failed,
        ]);
    }

    /**
     * Get per-provider summary.
     *
     * @return Collection<int, array{provider: string, sent: int, delivered: int, failed: int, success_rate: float}>
     */
    public function perProviderSummary(): Collection
    {
        $query = SmsLog::query()
            ->selectRaw('provider')
            ->selectRaw('COUNT(*) as sent')
            ->selectRaw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as delivered')
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            ->groupBy('provider');

        if ($this->fromDate) {
            $query->where('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate) {
            $query->where('created_at', '<=', $this->toDate);
        }

        return $query->get()->map(fn ($row) => [
            'provider' => $row->provider,
            'sent' => (int) $row->sent,
            'delivered' => (int) $row->delivered,
            'failed' => (int) $row->failed,
            'success_rate' => (int) $row->sent > 0
                ? round(((int) $row->delivered / (int) $row->sent) * 100, 2)
                : 0.0,
        ]);
    }
}
