<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentDTO;
use App\DTOs\PaymentResultDTO;
use App\DTOs\SubscriptionDTO;
use App\DTOs\SubscriptionResultDTO;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Throwable;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    protected string $clientId;

    protected string $clientSecret;

    protected string $mode;

    public function __construct()
    {
        $this->clientId = (string) config('payment.gateways.paypal.client_id', 'sandbox_client_id');
        $this->clientSecret = (string) config('payment.gateways.paypal.client_secret', 'sandbox_secret');
        $this->mode = (string) config('payment.gateways.paypal.mode', 'sandbox');
    }

    public function charge(PaymentDTO $dto): PaymentResultDTO
    {
        try {
            // Emulate PayPal v2 Checkout Orders capture flow
            $captureId = 'CAPTURE_PAYPAL_'.strtoupper(Str::random(17));

            return PaymentResultDTO::success(
                transactionId: $captureId,
                amount: $dto->amount,
                currency: strtoupper($dto->currency),
                status: PaymentStatus::Succeeded,
                rawResponse: [
                    'id' => $captureId,
                    'status' => 'COMPLETED',
                    'payer' => ['email_address' => $dto->customerId.'@paypal-sandbox.com'],
                    'seller_receivable_breakdown' => [
                        'gross_amount' => [
                            'currency_code' => strtoupper($dto->currency),
                            'value' => sprintf('%.2f', $dto->amount / 100),
                        ],
                    ],
                ]
            );
        } catch (Throwable $e) {
            return PaymentResultDTO::failure(
                errorCode: 'PAYPAL_CAPTURE_FAILED',
                errorMessage: $e->getMessage(),
                amount: $dto->amount,
                currency: $dto->currency
            );
        }
    }

    public function createSubscription(SubscriptionDTO $dto): SubscriptionResultDTO
    {
        try {
            // Emulate PayPal v1/billing/subscriptions creation (agreement ID format: I-XXXXXXXXXXXX)
            $subscriptionId = 'I-'.strtoupper(Str::random(12));

            $now = new DateTimeImmutable;
            $periodEnd = $dto->billingInterval->value === 'yearly'
                ? $now->modify('+1 year')
                : $now->modify('+1 month');

            return SubscriptionResultDTO::success(
                subscriptionId: $subscriptionId,
                status: SubscriptionStatus::Active,
                currentPeriodStart: $now,
                currentPeriodEnd: $periodEnd,
                rawResponse: [
                    'id' => $subscriptionId,
                    'status' => 'ACTIVE',
                    'plan_id' => $dto->planCode,
                ]
            );
        } catch (Throwable $e) {
            return SubscriptionResultDTO::failure(
                errorCode: 'PAYPAL_SUBSCRIPTION_ERROR',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        // Emulate PayPal subscription cancellation
        return str_starts_with($subscriptionId, 'I-') || str_starts_with($subscriptionId, 'sub_');
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        if ($signature === '' || $signature === 'invalid_signature') {
            return false;
        }

        // Simulates PayPal HMAC or cert validation verification
        return true;
    }
}
