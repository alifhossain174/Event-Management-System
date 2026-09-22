<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class InvoiceItem extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        $immutable = function (self $item) {
            if ($item->invoice()->value('status') !== 'draft') {
                throw new LogicException('Issued Invoice items are immutable.');
            }
        };
        self::updating($immutable);
        self::deleting($immutable);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'line_subtotal' => 'decimal:4',
            'discount_value' => 'decimal:6', 'discount_amount' => 'decimal:4',
            'taxable_amount' => 'decimal:4', 'tax_rate' => 'decimal:6',
            'tax_amount' => 'decimal:4', 'line_total' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
