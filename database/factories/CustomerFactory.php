<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'default_payment_method' => 'pm_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'currency' => 'USD',
        ];
    }
}
