<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Invoices\InvoicePdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function index(): View
    {
        $customers = Customer::orderBy('name')->get();
        $payments = Payment::with('customer')->latest()->take(10)->get();
        $subscriptions = Subscription::with('customer')->latest()->take(5)->get();
        $invoices = Invoice::with(['customer', 'payment'])->latest()->take(10)->get();
        $webhookEvents = WebhookEvent::latest()->take(10)->get();

        $stats = [
            'total_volume' => (int) Payment::where('status', PaymentStatus::Succeeded)->sum('amount'),
            'successful_payments' => Payment::where('status', PaymentStatus::Succeeded)->count(),
            'active_subscriptions' => Subscription::where('status', SubscriptionStatus::Active)->count(),
            'webhook_events' => WebhookEvent::count(),
            'dlq_failed' => WebhookEvent::where('status', WebhookEventStatus::Failed)->count(),
        ];

        return view('dashboard', compact(
            'customers',
            'payments',
            'subscriptions',
            'invoices',
            'webhookEvents',
            'stats'
        ));
    }

    public function downloadInvoice(string $id, InvoicePdfGenerator $pdfGenerator): Response
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::with(['customer', 'payment'])->findOrFail($id);

        if (! $invoice->pdf_path || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            // Generate on the fly if missing from storage disk
            $filename = $pdfGenerator->generate([
                'invoiceNumber' => $invoice->invoice_number,
                'date' => $invoice->issued_at->format('Y-m-d'),
                'status' => $invoice->status->value,
                'customerName' => $invoice->customer->name,
                'customerEmail' => $invoice->customer->email,
                'gateway' => $invoice->payment?->gateway ?? 'stripe',
                'transactionId' => $invoice->payment?->gateway_transaction_id,
                'description' => 'Invoice '.$invoice->invoice_number,
                'formattedSubtotal' => sprintf('%s %.2f', $invoice->currency, $invoice->amount / 100),
                'formattedTax' => sprintf('%s %.2f', $invoice->currency, $invoice->tax_amount / 100),
                'formattedTotal' => $invoice->formattedTotal(),
            ]);

            $invoice->update(['pdf_path' => $filename]);
        }

        return Storage::disk('local')->response(
            $invoice->pdf_path,
            $invoice->invoice_number.'.pdf'
        );
    }

    public function simulateWebhook(Request $request, InvoicePdfGenerator $pdfGenerator): JsonResponse
    {
        $eventType = (string) $request->input('event_type', 'invoice.payment_succeeded');
        $customer = Customer::first() ?? Customer::factory()->create();
        $subscription = Subscription::where('customer_id', $customer->id)->first() ?? Subscription::factory()->create(['customer_id' => $customer->id]);

        $eventId = 'evt_sim_'.Str::random(18);

        $payload = [
            'id' => $eventId,
            'object' => 'event',
            'type' => $eventType,
            'data' => [
                'object' => [
                    'id' => 'in_sim_'.Str::random(14),
                    'subscription' => $subscription->gateway_subscription_id,
                    'customer' => $customer->stripe_customer_id,
                    'payment_intent' => 'pi_sim_'.Str::random(14),
                    'amount_paid' => $subscription->price,
                    'currency' => strtolower($subscription->currency),
                    'period_start' => now()->timestamp,
                    'period_end' => now()->addMonth()->timestamp,
                ],
            ],
        ];

        /** @var WebhookEvent $event */
        $event = WebhookEvent::create([
            'event_id' => $eventId,
            'gateway' => 'stripe',
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => WebhookEventStatus::Pending,
        ]);

        // Process directly for the live dashboard demonstration
        $job = new ProcessStripeWebhookJob($event->id);
        $job->handle($pdfGenerator);

        return response()->json([
            'status' => 'success',
            'message' => "Webhook event [{$eventType}] simulated and processed asynchronously.",
            'event_id' => $eventId,
        ]);
    }

    public function retryDlq(): JsonResponse
    {
        Artisan::call('webhooks:retry', ['--all' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'All Dead Letter Queue (DLQ) events have been recovered and re-queued.',
        ]);
    }
}
