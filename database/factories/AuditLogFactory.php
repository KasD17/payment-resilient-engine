<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auditable_type' => Payment::class,
            'auditable_id' => (string) Str::uuid(),
            'event' => 'payment.status_changed',
            'old_values' => ['status' => 'processing'],
            'new_values' => ['status' => 'succeeded'],
            'actor' => 'stripe_webhook',
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Stripe/1.0 (+https://stripe.com/docs/webhooks)',
            'created_at' => now(),
        ];
    }
}
