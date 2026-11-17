<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\SubscriptionDTO;
use App\Enums\BillingInterval;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function store(CreateSubscriptionRequest $request, SubscriptionService $subscriptionService): JsonResponse
    {
        $dto = new SubscriptionDTO(
            customerId: (string) $request->validated('customer_id'),
            planCode: (string) $request->validated('plan_code'),
            price: (int) $request->validated('price'),
            currency: (string) $request->validated('currency', 'USD'),
            billingInterval: BillingInterval::from((string) $request->validated('billing_interval')),
            paymentMethodId: $request->validated('payment_method_id'),
            metadata: (array) $request->validated('metadata', [])
        );

        $subscription = $subscriptionService->createSubscription($dto);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function cancel(string $id, SubscriptionService $subscriptionService): JsonResponse
    {
        $subscription = $subscriptionService->cancelSubscription($id);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
