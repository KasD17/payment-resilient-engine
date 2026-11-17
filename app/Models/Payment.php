<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'subscription_id',
        'gateway',
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'payment_method_type',
        'error_code',
        'error_message',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the customer that made the payment.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the subscription associated with the payment.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the invoices associated with the payment.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }

    public function formattedAmount(): string
    {
        return sprintf('%s %.2f', strtoupper($this->currency), $this->amount / 100);
    }
}
