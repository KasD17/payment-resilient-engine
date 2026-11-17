<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'gateway',
        'event_type',
        'payload',
        'status',
        'attempts',
        'last_error',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebhookEventStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === WebhookEventStatus::Pending;
    }

    public function isProcessed(): bool
    {
        return $this->status === WebhookEventStatus::Processed;
    }

    public function isFailed(): bool
    {
        return $this->status === WebhookEventStatus::Failed;
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status' => WebhookEventStatus::Processed,
            'processed_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => WebhookEventStatus::Failed,
            'last_error' => $error,
        ]);
    }
}
