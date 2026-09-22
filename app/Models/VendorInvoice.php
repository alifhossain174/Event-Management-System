<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VendorInvoice extends Model implements TracksStatusHistory
{
    use HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'amount' => 'decimal:4'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(VendorAssignment::class, 'vendor_assignment_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return ['received' => ['approved', 'disputed', 'void'], 'disputed' => ['approved', 'void'], 'approved' => ['void'], 'void' => []];
    }
}
