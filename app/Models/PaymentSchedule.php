<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentSchedule extends Model implements TracksStatusHistory
{
    use HasStatusHistory;

    public const STATUSES = ['scheduled', 'partially_paid', 'paid', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:4', 'due_date' => 'immutable_date',
            'cancelled_at' => 'immutable_datetime',
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

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return [
            'scheduled' => ['partially_paid', 'paid', 'cancelled'],
            'partially_paid' => ['scheduled', 'paid'],
            'paid' => ['partially_paid'],
            'cancelled' => [],
        ];
    }
}
