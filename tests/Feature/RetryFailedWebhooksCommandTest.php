<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryFailedWebhooksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_no_argument_or_flag_provided(): void
    {
        $this->artisan('webhooks:retry')
            ->expectsOutputToContain('Please specify an [event_id] or use the [--all] flag')
            ->assertExitCode(1);
    }

    public function test_recovers_all_failed_webhooks_and_requeues_jobs(): void
    {
        Queue::fake();

        $failedEvent1 = WebhookEvent::factory()->create([
            'event_id' => 'evt_failed_001',
            'status' => WebhookEventStatus::Failed,
            'last_error' => 'Database connection dropped during lock',
        ]);

        $failedEvent2 = WebhookEvent::factory()->create([
            'event_id' => 'evt_failed_002',
            'status' => WebhookEventStatus::Failed,
            'last_error' => 'Timeout in payment webhook handler',
        ]);

        $processedEvent = WebhookEvent::factory()->create([
            'event_id' => 'evt_ok_003',
            'status' => WebhookEventStatus::Processed,
        ]);

        $this->artisan('webhooks:retry', ['--all' => true])
            ->expectsOutputToContain('Successfully recovered and re-queued [2] failed webhook event(s)')
            ->assertExitCode(0);

        // Verify status reset to pending and error cleared
        $this->assertSame(WebhookEventStatus::Pending, $failedEvent1->fresh()->status);
        $this->assertNull($failedEvent1->fresh()->last_error);

        $this->assertSame(WebhookEventStatus::Pending, $failedEvent2->fresh()->status);
        $this->assertNull($failedEvent2->fresh()->last_error);

        // Processed event untouched
        $this->assertSame(WebhookEventStatus::Processed, $processedEvent->fresh()->status);

        // Assert 2 jobs dispatched
        Queue::assertPushed(ProcessStripeWebhookJob::class, 2);
    }

    public function test_recovers_single_event_by_event_id(): void
    {
        Queue::fake();

        $targetEvent = WebhookEvent::factory()->create([
            'event_id' => 'evt_specific_target_999',
            'status' => WebhookEventStatus::Failed,
            'last_error' => 'Temporary network error',
        ]);

        $otherFailedEvent = WebhookEvent::factory()->create([
            'event_id' => 'evt_do_not_touch_888',
            'status' => WebhookEventStatus::Failed,
            'last_error' => 'Unrelated error',
        ]);

        $this->artisan('webhooks:retry', ['event_id' => 'evt_specific_target_999'])
            ->expectsOutputToContain('Successfully recovered and re-queued [1] failed webhook event(s)')
            ->assertExitCode(0);

        $this->assertSame(WebhookEventStatus::Pending, $targetEvent->fresh()->status);
        $this->assertSame(WebhookEventStatus::Failed, $otherFailedEvent->fresh()->status);

        Queue::assertPushed(ProcessStripeWebhookJob::class, 1);
    }
}
