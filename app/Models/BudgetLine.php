<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BudgetLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['planned_amount' => 'decimal:4', 'archived_at' => 'immutable_datetime'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(EventBudget::class, 'event_budget_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id')->withTrashed();
    }
}
