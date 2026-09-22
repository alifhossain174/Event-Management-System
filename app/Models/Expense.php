<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Expense extends Model
{
    public const STATUSES = ['draft', 'posted', 'void'];

    protected $table = 'event_expense_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4', 'transaction_date' => 'date', 'is_reversal' => 'boolean',
            'metadata' => 'array', 'approved_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id')->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id')->withTrashed();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
