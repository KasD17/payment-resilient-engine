<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PaymentStatus;

readonly class PaymentResultDTO
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $successful,
        public ?string $transactionId,
        public int $amount,
        public string $currency,
        public PaymentStatus $status,
        public array $rawResponse = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null
    ) {}

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function success(
        string $transactionId,
        int $amount,
        string $currency,
        PaymentStatus $status = PaymentStatus::Succeeded,
        array $rawResponse = []
    ): self {
        return new self(
            successful: true,
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            status: $status,
            rawResponse: $rawResponse
        );
    }

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function failure(
        string $errorCode,
        string $errorMessage,
        int $amount = 0,
        string $currency = 'USD',
        array $rawResponse = []
    ): self {
        return new self(
            successful: false,
            transactionId: null,
            amount: $amount,
            currency: $currency,
            status: PaymentStatus::Failed,
            rawResponse: $rawResponse,
            errorCode: $errorCode,
            errorMessage: $errorMessage
        );
    }
}
