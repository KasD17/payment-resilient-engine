<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class PaymentDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $amount,
        public string $currency,
        public string $paymentMethodId,
        public string $customerId,
        public ?string $description = null,
        public array $metadata = []
    ) {}
}
