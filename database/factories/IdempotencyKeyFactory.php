<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IdempotencyStatus;
use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => (string) Str::uuid(),
            'request_path' => '/api/v1/payments/charge',
            'request_params_hash' => hash('sha256', (string) Str::random(32)),
            'status' => IdempotencyStatus::Completed,
            'response_code' => 200,
            'response_body' => ['status' => 'success', 'data' => ['id' => (string) Str::uuid()]],
            'locked_at' => now(),
            'expires_at' => now()->addHours(24),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IdempotencyStatus::Processing,
            'response_code' => null,
            'response_body' => null,
            'locked_at' => now(),
        ]);
    }
}
