<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingInterval;
use App\Enums\IdempotencyStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEventStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_have_subscriptions_payments_and_invoices(): void
    {
        $customer = Customer::factory()->create();

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => BillingInterval::Monthly,
        ]);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'amount' => 5000,
            'status' => PaymentStatus::Succeeded,
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'payment_id' => $payment->id,
            'amount' => 5000,
            'tax_amount' => 500,
            'status' => InvoiceStatus::Paid,
        ]);

        $this->assertCount(1, $customer->subscriptions);
        $this->assertCount(1, $customer->payments);
        $this->assertCount(1, $customer->invoices);

        $this->assertTrue($subscription->isActive());
        $this->assertTrue($payment->isSuccessful());
        $this->assertSame(5500, $invoice->totalAmount());
        $this->assertSame('USD 50.00', $payment->formattedAmount());
    }

    public function test_idempotency_key_model_casts_and_scopes(): void
    {
        $activeKey = IdempotencyKey::factory()->create([
            'status' => IdempotencyStatus::Completed,
            'expires_at' => now()->addHour(),
        ]);

        $expiredKey = IdempotencyKey::factory()->create([
            'status' => IdempotencyStatus::Completed,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($activeKey->isCompleted());
        $this->assertFalse($activeKey->isExpired());
        $this->assertTrue($expiredKey->isExpired());

        $expiredCount = IdempotencyKey::expired()->count();
        $this->assertSame(1, $expiredCount);
    }

    public function test_webhook_event_lifecycle_and_helpers(): void
    {
        /** @var WebhookEvent $event */
        $event = WebhookEvent::factory()->create([
            'status' => WebhookEventStatus::Pending,
        ]);

        $this->assertTrue($event->isPending());

        $event->markAsProcessed();
        $this->assertTrue($event->fresh()->isProcessed());
        $this->assertNotNull($event->fresh()->processed_at);

        $event->markAsFailed('Simulated connection error');
        $this->assertTrue($event->fresh()->isFailed());
        $this->assertSame('Simulated connection error', $event->fresh()->last_error);
    }

    public function test_audit_log_records_polymorphic_events(): void
    {
        $payment = Payment::factory()->create();

        $log = AuditLog::record(
            model: $payment,
            event: 'payment.status_changed',
            oldValues: ['status' => 'processing'],
            newValues: ['status' => 'succeeded'],
            actor: 'stripe_webhook'
        );

        $this->assertSame(Payment::class, $log->auditable_type);
        $this->assertSame((string) $payment->id, $log->auditable_id);
        $this->assertSame('stripe_webhook', $log->actor);
        $this->assertSame(['status' => 'processing'], $log->old_values);
        $this->assertSame(['status' => 'succeeded'], $log->new_values);
    }
}
