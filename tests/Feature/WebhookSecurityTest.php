<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_webhook_with_invalid_signature(): void
    {
        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(false);

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $response = $this->call(
            method: 'POST',
            uri: '/api/v1/webhooks/stripe',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=invalid_signature',
            ],
            content: json_encode(['id' => 'evt_test_123', 'type' => 'payment_intent.succeeded'])
        );

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid webhook signature.',
                'code' => 'INVALID_WEBHOOK_SIGNATURE',
            ]);

        $this->assertDatabaseMissing('webhook_events', [
            'event_id' => 'evt_test_123',
        ]);
    }

    public function test_accepts_webhook_with_valid_signature_persists_event_and_dispatches_job(): void
    {
        Queue::fake();

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(true);

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $payload = [
            'id' => 'evt_legit_stripe_event_999',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_abc',
                    'amount' => 5000,
                ],
            ],
        ];

        $response = $this->call(
            method: 'POST',
            uri: '/api/v1/webhooks/stripe',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=valid_signature_hash',
            ],
            content: json_encode($payload)
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'received',
                'event_id' => 'evt_legit_stripe_event_999',
            ]);

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt_legit_stripe_event_999',
            'status' => 'pending',
            'event_type' => 'payment_intent.succeeded',
        ]);

        Queue::assertPushed(ProcessStripeWebhookJob::class);
    }

    public function test_webhook_deduplication_ignores_already_received_events(): void
    {
        Queue::fake();

        // Seed an event that was already persisted
        WebhookEvent::factory()->create([
            'event_id' => 'evt_already_exists_123',
            'status' => WebhookEventStatus::Processed,
        ]);

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(true);

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $payload = [
            'id' => 'evt_already_exists_123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => []],
        ];

        $response = $this->call(
            method: 'POST',
            uri: '/api/v1/webhooks/stripe',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=valid_signature_hash',
            ],
            content: json_encode($payload)
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'duplicate_ignored',
                'event_id' => 'evt_already_exists_123',
            ]);

        // Job must NOT be pushed for duplicate event
        Queue::assertNothingPushed();
    }
}
