<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'subscription_id' => $this->subscription_id,
            'gateway' => $this->gateway,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'amount' => $this->amount,
            'amount_formatted' => $this->formattedAmount(),
            'currency' => $this->currency,
            'status' => $this->status->value,
            'payment_method_type' => $this->payment_method_type,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
