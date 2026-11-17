<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\BillingInterval;

readonly class SubscriptionDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $customerId,
        public string $planCode,
        public int $price,
        public string $currency,
        public BillingInterval $billingInterval,
        public ?string $paymentMethodId = null,
        public array $metadata = []
    ) {}
}
