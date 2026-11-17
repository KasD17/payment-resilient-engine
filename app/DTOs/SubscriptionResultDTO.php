<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SubscriptionStatus;
use DateTimeInterface;

readonly class SubscriptionResultDTO
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $successful,
        public ?string $subscriptionId,
        public SubscriptionStatus $status,
        public ?DateTimeInterface $currentPeriodStart = null,
        public ?DateTimeInterface $currentPeriodEnd = null,
        public array $rawResponse = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null
    ) {}

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function success(
        string $subscriptionId,
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?DateTimeInterface $currentPeriodStart = null,
        ?DateTimeInterface $currentPeriodEnd = null,
        array $rawResponse = []
    ): self {
        return new self(
            successful: true,
            subscriptionId: $subscriptionId,
            status: $status,
            currentPeriodStart: $currentPeriodStart,
            currentPeriodEnd: $currentPeriodEnd,
            rawResponse: $rawResponse
        );
    }

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function failure(
        string $errorCode,
        string $errorMessage,
        array $rawResponse = []
    ): self {
        return new self(
            successful: false,
            subscriptionId: null,
            status: SubscriptionStatus::Incomplete,
            rawResponse: $rawResponse,
            errorCode: $errorCode,
            errorMessage: $errorMessage
        );
    }
}
