<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\SubscriptionResultDTO;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Subscription;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_subscription_successfully_with_idempotency(): void
    {
        $customer = Customer::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('createSubscription')
            ->once()
            ->andReturn(SubscriptionResultDTO::success(
                subscriptionId: 'sub_test_premium_123',
                status: SubscriptionStatus::Active,
                currentPeriodStart: new DateTimeImmutable,
                currentPeriodEnd: (new DateTimeImmutable)->modify('+1 month'),
                rawResponse: ['id' => 'sub_test_premium_123']
            ));

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $payload = [
            'customer_id' => $customer->id,
            'plan_code' => 'plan_enterprise_monthly',
            'price' => 9900,
            'currency' => 'USD',
            'billing_interval' => 'monthly',
            'payment_method_id' => 'pm_card_visa',
        ];

        $response = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.plan_code', 'plan_enterprise_monthly')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.gateway_subscription_id', 'sub_test_premium_123');

        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $customer->id,
            'plan_code' => 'plan_enterprise_monthly',
            'status' => 'active',
            'gateway_subscription_id' => 'sub_test_premium_123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'subscription.created',
        ]);
    }

    public function test_cancels_subscription_successfully(): void
    {
        $customer = Customer::factory()->create();
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'gateway_subscription_id' => 'sub_cancel_me_999',
            'status' => SubscriptionStatus::Active,
        ]);

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_cancel_me_999')
            ->andReturn(true);

        $this->app->instance(PaymentGatewayInterface::class, $gatewayMock);

        $response = $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'canceled');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'canceled',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'subscription.canceled',
        ]);
    }
}
