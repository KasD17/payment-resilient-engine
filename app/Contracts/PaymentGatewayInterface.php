<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\PaymentDTO;
use App\DTOs\PaymentResultDTO;
use App\DTOs\SubscriptionDTO;
use App\DTOs\SubscriptionResultDTO;

interface PaymentGatewayInterface
{
    /**
     * Charge a single one-time payment.
     */
    public function charge(PaymentDTO $dto): PaymentResultDTO;

    /**
     * Create a new recurring subscription.
     */
    public function createSubscription(SubscriptionDTO $dto): SubscriptionResultDTO;

    /**
     * Cancel an existing subscription.
     */
    public function cancelSubscription(string $subscriptionId): bool;

    /**
     * Verify cryptographic signature of a gateway webhook request.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;
}
