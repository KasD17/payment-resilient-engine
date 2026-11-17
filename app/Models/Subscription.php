<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'gateway',
        'gateway_subscription_id',
        'plan_code',
        'status',
        'price',
        'currency',
        'billing_interval',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_interval' => BillingInterval::class,
            'price' => 'integer',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns the subscription.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the payments associated with this subscription.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::Canceled;
    }
}
