<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'subscription_id' => null,
            'gateway' => 'stripe',
            'gateway_transaction_id' => 'pi_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'amount' => fake()->numberBetween(1000, 50000), // $10 to $500
            'currency' => 'USD',
            'status' => PaymentStatus::Succeeded,
            'payment_method_type' => 'card',
            'error_code' => null,
            'error_message' => null,
            'metadata' => [
                'ip' => fake()->ipv4(),
                'item_description' => fake()->sentence(3),
            ],
        ];
    }
}
