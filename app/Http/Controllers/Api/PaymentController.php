<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\PaymentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChargePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function charge(ChargePaymentRequest $request, PaymentService $paymentService): JsonResponse
    {
        $dto = new PaymentDTO(
            amount: (int) $request->validated('amount'),
            currency: (string) $request->validated('currency', 'USD'),
            paymentMethodId: (string) $request->validated('payment_method_id'),
            customerId: (string) $request->validated('customer_id'),
            description: $request->validated('description'),
            metadata: (array) $request->validated('metadata', [])
        );

        $payment = $paymentService->processCharge($dto);

        $statusCode = $payment->isSuccessful()
            ? Response::HTTP_CREATED
            : Response::HTTP_UNPROCESSABLE_ENTITY;

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode($statusCode);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::with(['customer', 'subscription'])->findOrFail($id);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
