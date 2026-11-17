<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'plan_code' => fake()->randomElement(['pro_monthly', 'business_monthly', 'enterprise_yearly']),
            'status' => SubscriptionStatus::Active,
            'price' => fake()->randomElement([2900, 4900, 9900]),
            'currency' => 'USD',
            'billing_interval' => BillingInterval::Monthly,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'canceled_at' => null,
        ];
    }
}
