<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, PaymentGatewayInterface $gateway): JsonResponse
    {
        $payload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = (string) config('payment.gateways.stripe.webhook_secret');

        // 1. Cryptographic signature check (< 5ms)
        if (! $gateway->verifyWebhookSignature($payload, $signature, $secret)) {
            return new JsonResponse([
                'error' => 'Invalid webhook signature.',
                'code' => 'INVALID_WEBHOOK_SIGNATURE',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($payload, true);

        if (! is_array($data) || ! isset($data['id'], $data['type'])) {
            return new JsonResponse([
                'error' => 'Malformed webhook payload.',
                'code' => 'MALFORMED_PAYLOAD',
            ], Response::HTTP_BAD_REQUEST);
        }

        $eventId = (string) $data['id'];
        $eventType = (string) $data['type'];

        // 2. Atomic Deduplication: If already received, return 200 OK immediately
        $existingEvent = WebhookEvent::where('event_id', $eventId)->first();

        if ($existingEvent) {
            return new JsonResponse([
                'status' => 'duplicate_ignored',
                'event_id' => $eventId,
            ], Response::HTTP_OK);
        }

        // 3. Persist raw event with status 'pending'
        /** @var WebhookEvent $webhookEvent */
        $webhookEvent = WebhookEvent::create([
            'event_id' => $eventId,
            'gateway' => 'stripe',
            'event_type' => $eventType,
            'payload' => $data,
            'status' => WebhookEventStatus::Pending,
        ]);

        // 4. Dispatch async processing job to queue
        ProcessStripeWebhookJob::dispatch($webhookEvent->id);

        // 5. Fast ACK (< 50ms)
        return new JsonResponse([
            'status' => 'received',
            'event_id' => $eventId,
        ], Response::HTTP_OK);
    }
}
