<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'customer_id',
        'invoice_number',
        'amount',
        'tax_amount',
        'currency',
        'status',
        'pdf_path',
        'issued_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * Get the customer associated with the invoice.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the payment associated with the invoice.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function totalAmount(): int
    {
        return $this->amount + $this->tax_amount;
    }

    public function formattedTotal(): string
    {
        return sprintf('%s %.2f', strtoupper($this->currency), $this->totalAmount() / 100);
    }
}
