<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentDTO;
use App\DTOs\SubscriptionDTO;
use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Services\Gateways\PayPalPaymentGateway;
use App\Services\Gateways\StripePaymentGateway;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_service_charges_via_paypal_strategy(): void
    {
        $customer = Customer::factory()->create();

        // Switch default gateway to PayPal dynamically
        config(['payment.default' => 'paypal']);

        $gateway = $this->app->make(PayPalPaymentGateway::class);
        $service = new PaymentService($gateway);

        $dto = new PaymentDTO(
            amount: 8500,
            currency: 'USD',
            paymentMethodId: 'paypal_token_123',
            customerId: $customer->id,
            description: 'PayPal Strategy Test Charge'
        );

        $payment = $service->processCharge($dto);

        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame(8500, $payment->amount);
        $this->assertStringStartsWith('CAPTURE_PAYPAL_', $payment->gateway_transaction_id);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'customer_id' => $customer->id,
            'gateway' => 'paypal',
            'amount' => 8500,
            'status' => 'succeeded',
        ]);
    }

    public function test_subscription_service_creates_via_paypal_strategy(): void
    {
        $customer = Customer::factory()->create();

        config(['payment.default' => 'paypal']);

        $gateway = $this->app->make(PayPalPaymentGateway::class);
        $service = new SubscriptionService($gateway);

        $dto = new SubscriptionDTO(
            customerId: $customer->id,
            planCode: 'plan_paypal_monthly',
            price: 4900,
            currency: 'USD',
            billingInterval: BillingInterval::Monthly,
            paymentMethodId: 'paypal_billing_token'
        );

        $subscription = $service->createSubscription($dto);

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('paypal', $subscription->gateway);
        $this->assertStringStartsWith('I-', $subscription->gateway_subscription_id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'gateway' => 'paypal',
            'plan_code' => 'plan_paypal_monthly',
            'status' => 'active',
        ]);
    }

    public function test_container_resolves_gateway_dynamically_based_on_config(): void
    {
        // 1. Default should be Stripe
        config(['payment.default' => 'stripe']);
        $stripeResolved = $this->app->make(PaymentGatewayInterface::class);
        $this->assertInstanceOf(StripePaymentGateway::class, $stripeResolved);

        // 2. Swapping config resolves PayPal
        config(['payment.default' => 'paypal']);
        $paypalResolved = $this->app->make(PaymentGatewayInterface::class);
        $this->assertInstanceOf(PayPalPaymentGateway::class, $paypalResolved);
    }
}
