<?php

declare(strict_types=1);

return [
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'stripe'),

    'gateways' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currency' => env('STRIPE_CURRENCY', 'USD'),
        ],

        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID', 'sandbox_paypal_client_id'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET', 'sandbox_paypal_secret'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID', 'sandbox_webhook_id'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
        ],
    ],

    'idempotency' => [
        'ttl_hours' => 24,
        'header' => 'Idempotency-Key',
    ],
];
