<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentResultDTO;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_requires_idempotency_key_header(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/payments/charge', [
            'amount' => 5000,
            'currency' => 'USD',
            'customer_id' => $customer->id,
            'payment_method_id' => 'pm_card_visa',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Missing required Idempotency-Key header.',
                'code' => 'MISSING_IDEMPOTENCY_KEY',
            ]);
    }

    public function test_successful_charge_creates_payment_and_audit_log(): void
    {
        $customer = Customer::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('charge')
            ->once()
            ->andReturn(PaymentResultDTO::success(
                transactionId: 'pi_test_123456789',
                amount: 5000,
                currency: 'USD',
                status: PaymentStatus::Succeeded,
                rawResponse: ['id' => 'pi_test_123456789', 'status' => 'succeeded']
            ));

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $response = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments/charge', [
                'amount' => 5000,
                'currency' => 'USD',
                'customer_id' => $customer->id,
                'payment_method_id' => 'pm_card_visa',
                'description' => 'Test Charge #1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', 5000)
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.gateway_transaction_id', 'pi_test_123456789');

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'amount' => 5000,
            'status' => 'succeeded',
            'gateway_transaction_id' => 'pi_test_123456789',
        ]);

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $idempotencyKey,
            'status' => 'completed',
            'response_code' => 201,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment.succeeded',
            'actor' => 'api',
        ]);
    }

    public function test_idempotency_replays_exact_cached_response_without_charging_again(): void
    {
        $customer = Customer::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        // Gateway MUST only be called ONCE despite two identical requests
        $gatewayMock->shouldReceive('charge')
            ->once()
            ->andReturn(PaymentResultDTO::success(
                transactionId: 'pi_test_replay_999',
                amount: 7500,
                currency: 'USD',
                status: PaymentStatus::Succeeded
            ));

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $payload = [
            'amount' => 7500,
            'currency' => 'USD',
            'customer_id' => $customer->id,
            'payment_method_id' => 'pm_card_mastercard',
        ];

        // 1st Call (Cache Miss -> Calls Gateway)
        $firstResponse = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments/charge', $payload);

        $firstResponse->assertStatus(201)
            ->assertJsonPath('data.amount', 7500);

        // 2nd Call (Cache Hit -> Replays response with Idempotent-Replay header)
        $secondResponse = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments/charge', $payload);

        $secondResponse->assertStatus(201)
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertHeader('X-Cache-Lookup', 'HIT')
            ->assertJsonPath('data.amount', 7500);

        // Assure only ONE payment row was ever created in the database
        $this->assertSame(1, Payment::where('customer_id', $customer->id)->count());
    }

    public function test_idempotency_detects_payload_tampering_with_same_key(): void
    {
        $customer = Customer::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        // Seed an existing completed idempotency key
        IdempotencyKey::factory()->create([
            'key' => $idempotencyKey,
            'request_path' => 'api/v1/payments/charge',
            'request_params_hash' => hash('sha256', json_encode(['amount' => 5000])),
            'response_code' => 201,
            'response_body' => ['status' => 'success'],
        ]);

        // Attempting to send same key with DIFFERENT amount
        $response = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments/charge', [
                'amount' => 99000,
                'currency' => 'USD',
                'customer_id' => $customer->id,
                'payment_method_id' => 'pm_card_visa',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'error' => 'Idempotency key was previously used with different request parameters.',
                'code' => 'IDEMPOTENCY_PAYLOAD_MISMATCH',
            ]);
    }

    public function test_handles_failed_gateway_charge_gracefully(): void
    {
        $customer = Customer::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('charge')
            ->once()
            ->andReturn(PaymentResultDTO::failure(
                errorCode: 'insufficient_funds',
                errorMessage: 'Your card has insufficient funds.',
                amount: 5000,
                currency: 'USD'
            ));

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $response = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments/charge', [
                'amount' => 5000,
                'currency' => 'USD',
                'customer_id' => $customer->id,
                'payment_method_id' => 'pm_card_visa',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'insufficient_funds');

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'status' => 'failed',
            'error_code' => 'insufficient_funds',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment.failed',
        ]);
    }
}
