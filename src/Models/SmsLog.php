<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Moffhub\SmsHandler\Database\Factories\SmsLogFactory;

/**
 * @property int $id
 * @property string $ulid
 * @property string|null $message_id
 * @property string $provider
 * @property string $to
 * @property string $message
 * @property bool $success
 * @property string|null $delivery_status
 * @property CarbonImmutable|null $scheduled_at
 * @property array|null $response
 * @property float|null $estimated_cost
 * @property int|null $segment_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder forProvider(string $provider)
 * @method static Builder forRecipient(string $phone)
 * @method static Builder failed()
 * @method static Builder delivered()
 * @method static Builder pending()
 * @method static Builder sent()
 * @method static Builder scheduled()
 * @method static Builder between(string $from, string $to)
 * @method static Builder recent(int $hours = 24)
 */
class SmsLog extends Model
{
    /** @use HasFactory<SmsLogFactory> */
    use HasFactory;

    protected static function newFactory(): SmsLogFactory
    {
        return SmsLogFactory::new();
    }

    protected $fillable = [
        'ulid',
        'message_id',
        'provider',
        'to',
        'message',
        'success',
        'delivery_status',
        'scheduled_at',
        'response',
        'estimated_cost',
        'segment_count',
    ];

    protected $casts = [
        'response' => 'array',
        'success' => 'boolean',
        'scheduled_at' => 'immutable_datetime',
        'estimated_cost' => 'float',
        'segment_count' => 'integer',
    ];

    /**
     * Scope: filter by provider name.
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope: filter by recipient phone number.
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeForRecipient(Builder $query, string $phone): Builder
    {
        return $query->where('to', $phone);
    }

    /**
     * Scope: where status is 'failed'.
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    /**
     * Scope: where delivery_status is 'delivered' or 'DeliveredToTerminal'.
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->whereIn('delivery_status', ['delivered', 'DeliveredToTerminal']);
    }

    /**
     * Scope: where delivery_status is 'pending' or null.
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('delivery_status', 'pending')
                ->orWhereNull('delivery_status');
        });
    }

    /**
     * Scope: where status is 'sent' or 'success' (success = true).
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('success', true);
    }

    /**
     * Scope: where scheduled_at is not null and in the future.
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now());
    }

    /**
     * Scope: filter by created_at date range.
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope: created within last N hours (default 24).
     *
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
