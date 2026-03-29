<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Moffhub\SmsHandler\Models\SmsLog;

/**
 * @extends Factory<SmsLog>
 */
class SmsLogFactory extends Factory
{
    protected $model = SmsLog::class;

    public function definition(): array
    {
        return [
            'ulid' => $this->faker->uuid(),
            'message_id' => $this->faker->uuid(),
            'provider' => 'advanta',
            'to' => $this->faker->e164PhoneNumber(),
            'message' => $this->faker->sentence(),
            'success' => true,
            'delivery_status' => 'delivered',
            'scheduled_at' => null,
            'response' => null,
            'estimated_cost' => null,
            'segment_count' => 1,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'success' => false,
            'delivery_status' => 'failed',
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'success' => true,
            'delivery_status' => 'delivered',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'success' => true,
            'delivery_status' => 'pending',
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'delivery_status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);
    }

    public function forProvider(string $provider): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => $provider,
        ]);
    }

    public function withCost(float $cost): static
    {
        return $this->state(fn (array $attributes): array => [
            'estimated_cost' => $cost,
        ]);
    }
}
