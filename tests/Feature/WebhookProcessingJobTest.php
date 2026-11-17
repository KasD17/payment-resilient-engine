<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Invoices\InvoicePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebhookProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_payment_intent_succeeded_event(): void
    {
        $customer = Customer::factory()->create();
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'gateway_transaction_id' => 'pi_test_webhook_success_123',
            'status' => PaymentStatus::Processing,
        ]);

        $webhookEvent = WebhookEvent::factory()->create([
            'event_id' => 'evt_pi_succeeded_001',
            'event_type' => 'payment_intent.succeeded',
            'payload' => [
                'id' => 'evt_pi_succeeded_001',
                'type' => 'payment_intent.succeeded',
                'data' => [
                    'object' => [
                        'id' => 'pi_test_webhook_success_123',
                        'amount' => 5000,
                        'currency' => 'usd',
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'status' => WebhookEventStatus::Pending,
        ]);

        $pdfGenerator = $this->app->make(InvoicePdfGenerator::class);
        $job = new ProcessStripeWebhookJob($webhookEvent->id);
        $job->handle($pdfGenerator);

        $this->assertTrue($webhookEvent->fresh()->isProcessed());
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => (string) $payment->id,
            'event' => 'payment.succeeded_via_webhook',
        ]);
    }

    public function test_job_processes_invoice_payment_succeeded_and_generates_pdf(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'stripe_customer_id' => 'cus_stripe_sub_owner_1',
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'gateway_subscription_id' => 'sub_stripe_sub_123',
            'status' => SubscriptionStatus::Incomplete,
        ]);

        $webhookEvent = WebhookEvent::factory()->create([
            'event_id' => 'evt_invoice_succeeded_002',
            'event_type' => 'invoice.payment_succeeded',
            'payload' => [
                'id' => 'evt_invoice_succeeded_002',
                'type' => 'invoice.payment_succeeded',
                'data' => [
                    'object' => [
                        'id' => 'in_stripe_inv_999',
                        'subscription' => 'sub_stripe_sub_123',
                        'customer' => 'cus_stripe_sub_owner_1',
                        'payment_intent' => 'pi_inv_paid_777',
                        'amount_paid' => 4900,
                        'currency' => 'usd',
                        'period_start' => now()->timestamp,
                        'period_end' => now()->addMonth()->timestamp,
                    ],
                ],
            ],
            'status' => WebhookEventStatus::Pending,
        ]);

        $pdfGenerator = $this->app->make(InvoicePdfGenerator::class);
        $job = new ProcessStripeWebhookJob($webhookEvent->id);
        $job->handle($pdfGenerator);

        $this->assertTrue($webhookEvent->fresh()->isProcessed());
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);

        // Verify payment and invoice created
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'gateway_transaction_id' => 'pi_inv_paid_777',
            'amount' => 4900,
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'amount' => 4900,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'subscription.renewed_via_webhook',
        ]);
    }

    public function test_job_processes_customer_subscription_deleted(): void
    {
        $customer = Customer::factory()->create();
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'gateway_subscription_id' => 'sub_stripe_deleted_456',
            'status' => SubscriptionStatus::Active,
        ]);

        $webhookEvent = WebhookEvent::factory()->create([
            'event_id' => 'evt_sub_deleted_003',
            'event_type' => 'customer.subscription.deleted',
            'payload' => [
                'id' => 'evt_sub_deleted_003',
                'type' => 'customer.subscription.deleted',
                'data' => [
                    'object' => [
                        'id' => 'sub_stripe_deleted_456',
                    ],
                ],
            ],
            'status' => WebhookEventStatus::Pending,
        ]);

        $pdfGenerator = $this->app->make(InvoicePdfGenerator::class);
        $job = new ProcessStripeWebhookJob($webhookEvent->id);
        $job->handle($pdfGenerator);

        $this->assertTrue($webhookEvent->fresh()->isProcessed());
        $this->assertSame(SubscriptionStatus::Canceled, $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->canceled_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'subscription.canceled_via_webhook',
        ]);
    }

    public function test_job_marks_event_as_failed_on_exception(): void
    {
        $webhookEvent = WebhookEvent::factory()->create([
            'event_id' => 'evt_broken_event_004',
            'status' => WebhookEventStatus::Pending,
        ]);

        $job = new ProcessStripeWebhookJob($webhookEvent->id);
        $job->failed(new \RuntimeException('Catastrophic database timeout'));

        $this->assertTrue($webhookEvent->fresh()->isFailed());
        $this->assertSame('Catastrophic database timeout', $webhookEvent->fresh()->last_error);
    }
}
