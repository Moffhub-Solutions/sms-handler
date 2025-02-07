<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ulid
 * @property string $provider
 * @property string $to
 * @property string $message
 * @property bool $success
 * @property array|null $response
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SmsLog extends Model
{
    protected $casts = [
        'response' => 'array',
        'success' => 'boolean',
    ];
}
