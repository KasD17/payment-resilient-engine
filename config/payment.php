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
    ],

    'idempotency' => [
        'ttl_hours' => 24,
        'header' => 'Idempotency-Key',
    ],
];
