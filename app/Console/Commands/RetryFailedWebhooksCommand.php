<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class RetryFailedWebhooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:retry
                            {event_id? : The specific webhook event_id to retry}
                            {--all : Retry all currently failed webhook events}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover and re-dispatch failed webhook events from the Dead Letter Queue (DLQ)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $eventId = $this->argument('event_id');
        $retryAll = $this->option('all');

        if (! $eventId && ! $retryAll) {
            $this->error('Please specify an [event_id] or use the [--all] flag to reprocess failed webhooks.');

            return self::FAILURE;
        }

        $query = WebhookEvent::where('status', WebhookEventStatus::Failed);

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $failedEvents = $query->get();

        if ($failedEvents->isEmpty()) {
            $this->info('No failed webhook events found matching the criteria.');

            return self::SUCCESS;
        }

        $tableData = [];

        foreach ($failedEvents as $event) {
            $tableData[] = [
                'ID' => $event->id,
                'Event ID' => $event->event_id,
                'Type' => $event->event_type,
                'Attempts' => $event->attempts,
                'Last Error' => substr((string) $event->last_error, 0, 45).'...',
            ];

            // Reset state to pending and clear error
            $event->update([
                'status' => WebhookEventStatus::Pending,
                'last_error' => null,
            ]);

            // Re-dispatch async job
            ProcessStripeWebhookJob::dispatch($event->id);
        }

        $this->info("Successfully recovered and re-queued [{$failedEvents->count()}] failed webhook event(s):");
        $this->table(['ID', 'Event ID', 'Type', 'Attempts', 'Last Error'], $tableData);

        return self::SUCCESS;
    }
}
