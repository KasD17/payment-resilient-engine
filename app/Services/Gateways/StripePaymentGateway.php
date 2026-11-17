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
use Stripe\Exception\CardException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

class StripePaymentGateway implements PaymentGatewayInterface
{
    protected StripeClient $client;

    public function __construct(?StripeClient $client = null)
    {
        $secret = config('payment.gateways.stripe.secret') ?? 'sk_test_placeholder';
        $this->client = $client ?? new StripeClient($secret);
    }

    public function charge(PaymentDTO $dto): PaymentResultDTO
    {
        try {
            $params = [
                'amount' => $dto->amount,
                'currency' => strtolower($dto->currency),
                'confirm' => true,
                'off_session' => true,
                'description' => $dto->description,
                'metadata' => $dto->metadata,
            ];

            if ($dto->paymentMethodId !== '') {
                $params['payment_method'] = $dto->paymentMethodId;
            }

            if ($dto->customerId !== '') {
                $params['customer'] = $dto->customerId;
            }

            $intent = $this->client->paymentIntents->create($params);

            $status = match ($intent->status) {
                'succeeded' => PaymentStatus::Succeeded,
                'processing' => PaymentStatus::Processing,
                default => PaymentStatus::Created,
            };

            return PaymentResultDTO::success(
                transactionId: $intent->id,
                amount: $intent->amount,
                currency: strtoupper($intent->currency),
                status: $status,
                rawResponse: $intent->toArray()
            );
        } catch (CardException $e) {
            return PaymentResultDTO::failure(
                errorCode: $e->getStripeCode() ?? 'card_declined',
                errorMessage: $e->getMessage(),
                amount: $dto->amount,
                currency: $dto->currency
            );
        } catch (Throwable $e) {
            return PaymentResultDTO::failure(
                errorCode: 'gateway_error',
                errorMessage: $e->getMessage(),
                amount: $dto->amount,
                currency: $dto->currency
            );
        }
    }

    public function createSubscription(SubscriptionDTO $dto): SubscriptionResultDTO
    {
        try {
            $params = [
                'customer' => $dto->customerId,
                'items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($dto->currency),
                            'product_data' => [
                                'name' => $dto->planCode,
                            ],
                            'unit_amount' => $dto->price,
                            'recurring' => [
                                'interval' => $dto->billingInterval->value,
                            ],
                        ],
                    ],
                ],
                'metadata' => $dto->metadata,
            ];

            if ($dto->paymentMethodId) {
                $params['default_payment_method'] = $dto->paymentMethodId;
            }

            $subscription = $this->client->subscriptions->create($params);

            $status = match ($subscription->status) {
                'active' => SubscriptionStatus::Active,
                'trialing' => SubscriptionStatus::Trialing,
                'past_due' => SubscriptionStatus::PastDue,
                default => SubscriptionStatus::Incomplete,
            };

            $currentPeriodStart = isset($subscription->current_period_start)
                ? (new DateTimeImmutable)->setTimestamp($subscription->current_period_start)
                : null;

            $currentPeriodEnd = isset($subscription->current_period_end)
                ? (new DateTimeImmutable)->setTimestamp($subscription->current_period_end)
                : null;

            return SubscriptionResultDTO::success(
                subscriptionId: $subscription->id,
                status: $status,
                currentPeriodStart: $currentPeriodStart,
                currentPeriodEnd: $currentPeriodEnd,
                rawResponse: $subscription->toArray()
            );
        } catch (Throwable $e) {
            return SubscriptionResultDTO::failure(
                errorCode: 'subscription_creation_failed',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            $this->client->subscriptions->cancel($subscriptionId);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        try {
            Webhook::constructEvent($payload, $signature, $secret);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
