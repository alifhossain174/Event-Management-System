<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VendorWorkOrder extends Model implements TracksStatusHistory
{
    use HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime', 'issued_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(VendorAssignment::class, 'vendor_assignment_id');
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return [
            'draft' => ['issued', 'cancelled'], 'issued' => ['accepted', 'in_progress', 'cancelled'],
            'accepted' => ['in_progress', 'cancelled'], 'in_progress' => ['completed', 'cancelled'],
            'completed' => [], 'cancelled' => [],
        ];
    }
}
