<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VendorContract extends Model implements TracksStatusHistory
{
    use HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'expiry_date' => 'date'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(VendorAssignment::class, 'vendor_assignment_id');
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
        return ['active' => ['expired', 'terminated'], 'expired' => [], 'terminated' => []];
    }
}
