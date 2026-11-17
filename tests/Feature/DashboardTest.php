<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEventStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_successfully_with_metrics_and_views(): void
    {
        $customer = Customer::factory()->create();
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 5000,
            'status' => PaymentStatus::Succeeded,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('Payment Resilient Engine')
            ->assertSee('Simulador de Idempotencia en Vivo')
            ->assertSee('$50.00');
    }

    public function test_can_download_invoice_pdf(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 5000,
            'status' => InvoiceStatus::Paid,
            'pdf_path' => null,
        ]);

        $response = $this->get("/invoices/{$invoice->id}/download");

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotNull($invoice->fresh()->pdf_path);
    }

    public function test_can_simulate_webhook_event_from_dashboard(): void
    {
        $response = $this->postJson('/dashboard/simulate-webhook', [
            'event_type' => 'invoice.payment_succeeded',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('webhook_events', [
            'event_type' => 'invoice.payment_succeeded',
            'status' => WebhookEventStatus::Processed->value,
        ]);
    }

    public function test_can_retry_dlq_from_dashboard(): void
    {
        WebhookEvent::factory()->create([
            'status' => WebhookEventStatus::Failed,
            'last_error' => 'Temporary network error',
        ]);

        $response = $this->postJson('/dashboard/retry-dlq');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
