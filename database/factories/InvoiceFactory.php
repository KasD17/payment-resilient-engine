<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'customer_id' => Customer::factory(),
            'invoice_number' => 'INV-'.date('Y').'-'.fake()->unique()->numerify('#####'),
            'amount' => 5000,
            'tax_amount' => 0,
            'currency' => 'USD',
            'status' => InvoiceStatus::Paid,
            'pdf_path' => 'invoices/'.fake()->uuid().'.pdf',
            'issued_at' => now(),
        ];
    }
}
