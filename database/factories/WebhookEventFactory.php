<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventId = 'evt_'.fake()->regexify('[A-Za-z0-9]{18}');

        return [
            'event_id' => $eventId,
            'gateway' => 'stripe',
            'event_type' => 'payment_intent.succeeded',
            'payload' => [
                'id' => $eventId,
                'object' => 'event',
                'type' => 'payment_intent.succeeded',
                'data' => [
                    'object' => [
                        'id' => 'pi_'.fake()->regexify('[A-Za-z0-9]{14}'),
                        'amount' => 5000,
                        'currency' => 'usd',
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'status' => WebhookEventStatus::Pending,
            'attempts' => 0,
            'last_error' => null,
            'processed_at' => null,
        ];
    }
}
