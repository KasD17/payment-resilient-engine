<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\SubscriptionDTO;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SubscriptionService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}

    /**
     * Create a new subscription with atomic state management and audit logging.
     */
    public function createSubscription(SubscriptionDTO $dto): Subscription
    {
        $customer = Customer::find($dto->customerId);

        if (! $customer) {
            throw new InvalidArgumentException("Customer [{$dto->customerId}] not found.");
        }

        // Delegate to gateway
        $result = $this->gateway->createSubscription($dto);

        if (! $result->successful) {
            throw new RuntimeException("Failed to create subscription with gateway: {$result->errorMessage}");
        }

        return DB::transaction(function () use ($dto, $customer, $result) {
            /** @var Subscription $subscription */
            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'gateway' => config('payment.default', 'stripe'),
                'gateway_subscription_id' => $result->subscriptionId,
                'plan_code' => $dto->planCode,
                'status' => $result->status,
                'price' => $dto->price,
                'currency' => strtoupper($dto->currency),
                'billing_interval' => $dto->billingInterval,
                'current_period_start' => $result->currentPeriodStart,
                'current_period_end' => $result->currentPeriodEnd,
            ]);

            AuditLog::record(
                model: $subscription,
                event: 'subscription.created',
                oldValues: null,
                newValues: [
                    'status' => $subscription->status->value,
                    'gateway_subscription_id' => $result->subscriptionId,
                    'plan_code' => $dto->planCode,
                ],
                actor: 'api'
            );

            return $subscription;
        });
    }

    /**
     * Cancel an existing subscription.
     */
    public function cancelSubscription(string $subscriptionId): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::where('id', $subscriptionId)
            ->orWhere('gateway_subscription_id', $subscriptionId)
            ->firstOrFail();

        if ($subscription->gateway_subscription_id) {
            $this->gateway->cancelSubscription($subscription->gateway_subscription_id);
        }

        return DB::transaction(function () use ($subscription) {
            /** @var Subscription $locked */
            $locked = Subscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();

            $oldStatus = $locked->status;
            $locked->update([
                'status' => SubscriptionStatus::Canceled,
                'canceled_at' => now(),
            ]);

            AuditLog::record(
                model: $locked,
                event: 'subscription.canceled',
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => SubscriptionStatus::Canceled->value],
                actor: 'api'
            );

            return $locked;
        });
    }
}
