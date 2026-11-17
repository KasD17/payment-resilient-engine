<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\IdempotencyStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic fintech test data.
     */
    public function run(): void
    {
        // 1. Base test user
        User::firstOrCreate(
            ['email' => 'admin@paymentengine.com'],
            ['name' => 'Fintech Administrator', 'password' => bcrypt('password')]
        );

        // 2. Primary Demo Customer
        $demoCustomer = Customer::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            [
                'name' => 'Acme Corporation',
                'email' => 'billing@acmecorp.com',
                'stripe_customer_id' => 'cus_acme_stripe_demo',
                'default_payment_method' => 'pm_card_visa',
                'currency' => 'USD',
            ]
        );

        // 3. Subscriptions for Demo Customer
        $activeSub = Subscription::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000010'],
            [
                'customer_id' => $demoCustomer->id,
                'gateway' => 'stripe',
                'gateway_subscription_id' => 'sub_demo_enterprise_pro',
                'plan_code' => 'plan_enterprise_pro_monthly',
                'status' => SubscriptionStatus::Active,
                'price' => 9900,
                'currency' => 'USD',
                'billing_interval' => BillingInterval::Monthly,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->addMonth()->startOfMonth(),
            ]
        );

        // 4. Sample Successful Payment
        $successfulPayment = Payment::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000020'],
            [
                'customer_id' => $demoCustomer->id,
                'subscription_id' => $activeSub->id,
                'gateway' => 'stripe',
                'gateway_transaction_id' => 'pi_demo_charge_success_001',
                'amount' => 9900,
                'currency' => 'USD',
                'status' => PaymentStatus::Succeeded,
                'payment_method_type' => 'card',
                'metadata' => ['order_source' => 'web_checkout'],
            ]
        );

        // 5. Sample Invoice
        Invoice::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000030'],
            [
                'customer_id' => $demoCustomer->id,
                'payment_id' => $successfulPayment->id,
                'invoice_number' => 'INV-2026-00001',
                'amount' => 9900,
                'tax_amount' => 0,
                'currency' => 'USD',
                'status' => InvoiceStatus::Paid,
                'pdf_path' => 'invoices/INV-2026-00001.pdf',
                'issued_at' => now(),
            ]
        );

        // 6. Audit Logs
        AuditLog::record(
            model: $successfulPayment,
            event: 'payment.succeeded',
            oldValues: ['status' => 'processing'],
            newValues: ['status' => 'succeeded'],
            actor: 'seeder'
        );

        // 7. Sample Idempotency Key for Cache Hit testing
        $demoIdempotencyKey = 'demo-idempotency-key-12345';
        $params = [
            'amount' => 5000,
            'currency' => 'USD',
            'customer_id' => $demoCustomer->id,
            'payment_method_id' => 'pm_card_visa',
        ];
        ksort($params);

        IdempotencyKey::firstOrCreate(
            ['key' => $demoIdempotencyKey],
            [
                'request_path' => 'api/v1/payments/charge',
                'request_params_hash' => hash('sha256', json_encode($params)),
                'status' => IdempotencyStatus::Completed,
                'response_code' => 201,
                'response_body' => [
                    'data' => [
                        'id' => (string) Str::uuid(),
                        'customer_id' => $demoCustomer->id,
                        'amount' => 5000,
                        'amount_formatted' => 'USD 50.00',
                        'status' => 'succeeded',
                        'gateway' => 'stripe',
                        'gateway_transaction_id' => 'pi_demo_cached_001',
                    ],
                ],
                'locked_at' => now(),
                'expires_at' => now()->addHours(24),
            ]
        );

        // 8. Generate additional random test records
        Customer::factory(3)->create()->each(function (Customer $customer): void {
            Subscription::factory()->create(['customer_id' => $customer->id]);
            Payment::factory(2)->create(['customer_id' => $customer->id]);
        });
    }
}
