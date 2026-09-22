<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalaryRecord extends Model implements TracksStatusHistory
{
    use HasFactory, HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_starts_on' => 'date', 'period_ends_on' => 'date', 'paid_on' => 'date', 'amount' => 'decimal:4'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id')->withTrashed();
    }

    public function statusHistoryColumn(): string
    {
        return 'payment_status';
    }

    public function allowedStatusTransitions(): array
    {
        return ['due' => ['paid', 'void'], 'paid' => ['void'], 'void' => []];
    }
}
