<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Payment Endpoints (Protected by Idempotency Key Engine)
    Route::post('/payments/charge', [PaymentController::class, 'charge'])
        ->middleware('idempotent')
        ->name('api.v1.payments.charge');

    Route::get('/payments/{id}', [PaymentController::class, 'show'])
        ->name('api.v1.payments.show');

    // Subscription Endpoints
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])
        ->middleware('idempotent')
        ->name('api.v1.subscriptions.store');

    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel'])
        ->name('api.v1.subscriptions.cancel');

    // Resilient Webhook Ingestion Endpoint
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->name('api.v1.webhooks.stripe');
});
