<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ulid
 * @property string|null $message_id
 * @property string $provider
 * @property string $to
 * @property string $message
 * @property bool $success
 * @property string|null $delivery_status
 * @property array|null $response
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SmsLog extends Model
{
    protected $fillable = [
        'ulid',
        'message_id',
        'provider',
        'to',
        'message',
        'success',
        'delivery_status',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
        'success' => 'boolean',
    ];
}
