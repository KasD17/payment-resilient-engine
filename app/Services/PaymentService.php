<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentDTO;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}

    /**
     * Process a one-time charge with atomic transaction and audit trail.
     */
    public function processCharge(PaymentDTO $dto): Payment
    {
        $customer = Customer::find($dto->customerId);

        if (! $customer) {
            throw new InvalidArgumentException("Customer [{$dto->customerId}] not found.");
        }

        // 1. Create payment record in 'processing' status
        /** @var Payment $payment */
        $payment = DB::transaction(function () use ($dto, $customer) {
            return Payment::create([
                'customer_id' => $customer->id,
                'gateway' => config('payment.default', 'stripe'),
                'amount' => $dto->amount,
                'currency' => strtoupper($dto->currency),
                'status' => PaymentStatus::Processing,
                'payment_method_type' => 'card',
                'metadata' => $dto->metadata,
            ]);
        });

        // 2. Delegate to Gateway Strategy
        $result = $this->gateway->charge($dto);

        // 3. Atomically update payment state with pessimistic lock
        return DB::transaction(function () use ($payment, $result) {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            $oldStatus = $lockedPayment->status;

            if ($result->successful) {
                $lockedPayment->update([
                    'status' => PaymentStatus::Succeeded,
                    'gateway_transaction_id' => $result->transactionId,
                ]);

                AuditLog::record(
                    model: $lockedPayment,
                    event: 'payment.succeeded',
                    oldValues: ['status' => $oldStatus->value],
                    newValues: [
                        'status' => PaymentStatus::Succeeded->value,
                        'transaction_id' => $result->transactionId,
                    ],
                    actor: 'api'
                );
            } else {
                $lockedPayment->update([
                    'status' => PaymentStatus::Failed,
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                ]);

                AuditLog::record(
                    model: $lockedPayment,
                    event: 'payment.failed',
                    oldValues: ['status' => $oldStatus->value],
                    newValues: [
                        'status' => PaymentStatus::Failed->value,
                        'error_code' => $result->errorCode,
                        'error_message' => $result->errorMessage,
                    ],
                    actor: 'api'
                );
            }

            return $lockedPayment->fresh();
        });
    }
}
