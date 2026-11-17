<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEventStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Invoices\InvoicePdfGenerator;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $webhookEventId
    ) {}

    public function handle(InvoicePdfGenerator $pdfGenerator): void
    {
        /** @var WebhookEvent|null $event */
        $event = WebhookEvent::find($this->webhookEventId);

        if (! $event || $event->isProcessed()) {
            return;
        }

        $event->update([
            'status' => WebhookEventStatus::Processing,
            'attempts' => $event->attempts + 1,
        ]);

        $payload = $event->payload;
        $eventType = $event->event_type;
        $object = $payload['data']['object'] ?? [];

        try {
            match ($eventType) {
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($object),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($object),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($object, $pdfGenerator),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
                default => Log::info("Unhandled webhook event type: {$eventType}", ['event_id' => $event->event_id]),
            };

            $event->markAsProcessed();
        } catch (Throwable $e) {
            $event->update([
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handlePaymentIntentSucceeded(array $object): void
    {
        $transactionId = $object['id'] ?? null;

        if (! $transactionId) {
            return;
        }

        DB::transaction(function () use ($transactionId, $object) {
            /** @var Payment|null $payment */
            $payment = Payment::where('gateway_transaction_id', $transactionId)->lockForUpdate()->first();

            if (! $payment) {
                // If payment was initiated externally, associate with customer if exists
                $stripeCustomerId = $object['customer'] ?? null;
                $customer = $stripeCustomerId ? Customer::where('stripe_customer_id', $stripeCustomerId)->first() : null;

                if ($customer) {
                    $payment = Payment::create([
                        'customer_id' => $customer->id,
                        'gateway' => 'stripe',
                        'gateway_transaction_id' => $transactionId,
                        'amount' => (int) ($object['amount'] ?? 0),
                        'currency' => strtoupper((string) ($object['currency'] ?? 'USD')),
                        'status' => PaymentStatus::Succeeded,
                        'payment_method_type' => $object['payment_method_types'][0] ?? 'card',
                    ]);
                }
            } else {
                $oldStatus = $payment->status;
                $payment->update([
                    'status' => PaymentStatus::Succeeded,
                ]);

                AuditLog::record(
                    model: $payment,
                    event: 'payment.succeeded_via_webhook',
                    oldValues: ['status' => $oldStatus->value],
                    newValues: ['status' => PaymentStatus::Succeeded->value],
                    actor: 'stripe_webhook'
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handlePaymentIntentFailed(array $object): void
    {
        $transactionId = $object['id'] ?? null;

        if (! $transactionId) {
            return;
        }

        DB::transaction(function () use ($transactionId, $object) {
            /** @var Payment|null $payment */
            $payment = Payment::where('gateway_transaction_id', $transactionId)->lockForUpdate()->first();

            if ($payment) {
                $oldStatus = $payment->status;
                $lastError = $object['last_payment_error'] ?? [];

                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'error_code' => $lastError['code'] ?? 'payment_failed',
                    'error_message' => $lastError['message'] ?? 'Payment failed',
                ]);

                AuditLog::record(
                    model: $payment,
                    event: 'payment.failed_via_webhook',
                    oldValues: ['status' => $oldStatus->value],
                    newValues: [
                        'status' => PaymentStatus::Failed->value,
                        'error_code' => $payment->error_code,
                    ],
                    actor: 'stripe_webhook'
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handleInvoicePaymentSucceeded(array $object, InvoicePdfGenerator $pdfGenerator): void
    {
        $subscriptionId = $object['subscription'] ?? null;
        $amountPaid = (int) ($object['amount_paid'] ?? 0);
        $currency = strtoupper((string) ($object['currency'] ?? 'USD'));
        $invoiceNumber = 'INV-'.date('Y').'-'.str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($subscriptionId, $object, $amountPaid, $currency, $invoiceNumber, $pdfGenerator) {
            /** @var Subscription|null $subscription */
            $subscription = $subscriptionId
                ? Subscription::where('gateway_subscription_id', $subscriptionId)->lockForUpdate()->first()
                : null;

            $customer = $subscription
                ? $subscription->customer
                : Customer::where('stripe_customer_id', $object['customer'] ?? '')->first();

            if (! $customer) {
                return;
            }

            if ($subscription) {
                $oldStatus = $subscription->status;
                $periodStart = isset($object['period_start']) ? (new DateTimeImmutable)->setTimestamp((int) $object['period_start']) : now();
                $periodEnd = isset($object['period_end']) ? (new DateTimeImmutable)->setTimestamp((int) $object['period_end']) : now()->addMonth();

                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                ]);

                AuditLog::record(
                    model: $subscription,
                    event: 'subscription.renewed_via_webhook',
                    oldValues: ['status' => $oldStatus->value],
                    newValues: ['status' => SubscriptionStatus::Active->value],
                    actor: 'stripe_webhook'
                );
            }

            /** @var Payment $payment */
            $payment = Payment::create([
                'customer_id' => $customer->id,
                'subscription_id' => $subscription?->id,
                'gateway' => 'stripe',
                'gateway_transaction_id' => $object['payment_intent'] ?? null,
                'amount' => $amountPaid,
                'currency' => $currency,
                'status' => PaymentStatus::Succeeded,
                'payment_method_type' => 'card',
                'metadata' => ['stripe_invoice_id' => $object['id'] ?? null],
            ]);

            // Generate PDF Invoice
            $pdfPath = $pdfGenerator->generate([
                'invoiceNumber' => $invoiceNumber,
                'date' => now()->format('Y-m-d'),
                'status' => 'paid',
                'customerName' => $customer->name,
                'customerEmail' => $customer->email,
                'gateway' => 'stripe',
                'transactionId' => $payment->gateway_transaction_id,
                'description' => $subscription ? "Subscription Renewal - {$subscription->plan_code}" : 'Direct Invoice Payment',
                'formattedSubtotal' => sprintf('%s %.2f', $currency, $amountPaid / 100),
                'formattedTax' => sprintf('%s 0.00', $currency),
                'formattedTotal' => sprintf('%s %.2f', $currency, $amountPaid / 100),
            ]);

            Invoice::create([
                'payment_id' => $payment->id,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $amountPaid,
                'tax_amount' => 0,
                'currency' => $currency,
                'status' => InvoiceStatus::Paid,
                'pdf_path' => $pdfPath,
                'issued_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handleSubscriptionDeleted(array $object): void
    {
        $subscriptionId = $object['id'] ?? null;

        if (! $subscriptionId) {
            return;
        }

        DB::transaction(function () use ($subscriptionId) {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->lockForUpdate()->first();

            if ($subscription) {
                $oldStatus = $subscription->status;
                $subscription->update([
                    'status' => SubscriptionStatus::Canceled,
                    'canceled_at' => now(),
                ]);

                AuditLog::record(
                    model: $subscription,
                    event: 'subscription.canceled_via_webhook',
                    oldValues: ['status' => $oldStatus->value],
                    newValues: ['status' => SubscriptionStatus::Canceled->value],
                    actor: 'stripe_webhook'
                );
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Failed to process webhook event [{$this->webhookEventId}]: ".$exception->getMessage(), [
            'exception' => $exception,
        ]);

        $event = WebhookEvent::find($this->webhookEventId);
        $event?->markAsFailed($exception->getMessage());
    }
}
