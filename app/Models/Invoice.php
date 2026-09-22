<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    public const STATUSES = ['draft', 'issued', 'partially_paid', 'paid', 'cancelled', 'credited'];

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $invoice) {
            if ($invoice->getOriginal('status') === 'draft') {
                return;
            }

            $allowed = [
                'status', 'cancelled_by_user_id', 'cancelled_at', 'cancellation_reason',
                'credited_by_user_id', 'credited_at', 'credit_reason', 'updated_at',
            ];
            if (array_diff(array_keys($invoice->getDirty()), $allowed) !== []) {
                throw new LogicException('Issued Invoice snapshots are immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Invoice records cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date', 'due_date' => 'immutable_date',
            'default_tax_rate' => 'decimal:6', 'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4', 'taxable_total' => 'decimal:4',
            'tax_total' => 'decimal:4', 'total' => 'decimal:4',
            'issued_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
            'credited_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function creditFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_for_invoice_id');
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(self::class, 'credit_for_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(InvoiceStatusHistory::class)->orderByDesc('changed_at');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(InvoiceDelivery::class)->orderByDesc('attempted_at');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id')->withTrashed();
    }

    public function displayStatus(?\DateTimeInterface $asOf = null): string
    {
        if (in_array($this->status, ['issued', 'partially_paid'], true)
            && $this->due_date?->lt($asOf ?? now()->startOfDay())) {
            return 'overdue';
        }

        return $this->status;
    }
}
