<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdempotencyStatus;
use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'request_path',
        'request_params_hash',
        'status',
        'response_code',
        'response_body',
        'locked_at',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'response_code' => 'integer',
            'response_body' => 'array',
            'locked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isProcessing(): bool
    {
        return $this->status === IdempotencyStatus::Processing;
    }

    public function isCompleted(): bool
    {
        return $this->status === IdempotencyStatus::Completed;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Scope a query to only include expired idempotency keys.
     *
     * @param  Builder<IdempotencyKey>  $query
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }
}
