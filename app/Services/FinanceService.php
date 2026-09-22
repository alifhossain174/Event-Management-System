<?php

namespace App\Services;

use App\Models\BudgetLine;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventBudget;
use App\Models\Expense;
use App\Models\FinanceCategory;
use App\Models\Income;
use App\Models\StatusHistory;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinanceService
{
    public function __construct(private readonly AuditService $audit) {}

    public function createBudget(Event $event, User $actor): EventBudget
    {
        return DB::transaction(function () use ($event, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            if ($event->isOperationallyReadOnly()) {
                throw ValidationException::withMessages(['event' => 'Completed, cancelled, or archived Events are read-only.']);
            }

            $budget = EventBudget::query()->firstOrCreate(
                ['event_id' => $event->getKey()],
                ['currency_code' => $event->currency_code, 'status' => 'draft', 'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey()],
            );

            if ($budget->wasRecentlyCreated) {
                foreach ($event->template_snapshot['budget_lines'] ?? [] as $order => $line) {
                    if (! is_array($line) || ! in_array($line['direction'] ?? null, ['income', 'expense'], true)) {
                        continue;
                    }
                    $amount = DecimalMath::normalize((string) ($line['amount'] ?? '0'));
                    $budget->lines()->create([
                        'event_id' => $event->getKey(), 'direction' => $line['direction'],
                        'description' => (string) ($line['label'] ?? 'Template budget line'),
                        'planned_amount' => $amount, 'currency_code' => $event->currency_code,
                        'sort_order' => ($order + 1) * 10, 'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey(),
                    ]);
                }
                $this->audit->record('finance.budget_created', $budget, [], ['event_id' => $event->getKey(), 'currency_code' => $event->currency_code], $actor);
            }

            return $budget->fresh('lines.category');
        }, 3);
    }

    public function addBudgetLine(EventBudget $budget, array $data, User $actor): BudgetLine
    {
        return DB::transaction(function () use ($budget, $data, $actor) {
            $budget = EventBudget::query()->lockForUpdate()->findOrFail($budget->getKey());
            $this->ensureEditableBudget($budget);
            $this->ensureCategoryDirection((int) $data['finance_category_id'], $data['direction']);

            $line = $budget->lines()->create([
                'event_id' => $budget->event_id, 'finance_category_id' => $data['finance_category_id'],
                'direction' => $data['direction'], 'description' => $data['description'],
                'planned_amount' => DecimalMath::normalize($data['planned_amount']),
                'currency_code' => $budget->currency_code, 'sort_order' => $data['sort_order'] ?? 0,
                'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('finance.budget_line_created', $line, [], $this->lineSnapshot($line), $actor);

            return $line->load('category');
        }, 3);
    }

    public function updateBudgetLine(BudgetLine $line, array $data, User $actor): BudgetLine
    {
        return DB::transaction(function () use ($line, $data, $actor) {
            $line = BudgetLine::query()->lockForUpdate()->findOrFail($line->getKey());
            $budget = EventBudget::query()->lockForUpdate()->findOrFail($line->event_budget_id);
            $this->ensureEditableBudget($budget);
            $this->ensureCategoryDirection((int) $data['finance_category_id'], $data['direction']);
            $before = $this->lineSnapshot($line);
            $line->update([
                'finance_category_id' => $data['finance_category_id'], 'direction' => $data['direction'],
                'description' => $data['description'], 'planned_amount' => DecimalMath::normalize($data['planned_amount']),
                'sort_order' => $data['sort_order'] ?? 0, 'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('finance.budget_line_updated', $line, $before, $this->lineSnapshot($line), $actor);

            return $line->fresh('category');
        }, 3);
    }

    public function archiveBudgetLine(BudgetLine $line, string $reason, User $actor): BudgetLine
    {
        return DB::transaction(function () use ($line, $reason, $actor) {
            $line = BudgetLine::query()->lockForUpdate()->findOrFail($line->getKey());
            $this->ensureEditableBudget(EventBudget::query()->lockForUpdate()->findOrFail($line->event_budget_id));
            $line->update(['archived_at' => now(), 'archived_by_user_id' => $actor->getKey(), 'archive_reason' => $reason]);
            $this->audit->record('finance.budget_line_archived', $line, [], ['reason' => $reason], $actor);

            return $line->refresh();
        }, 3);
    }

    public function approveBudget(EventBudget $budget, User $actor): EventBudget
    {
        return DB::transaction(function () use ($budget, $actor) {
            $budget = EventBudget::query()->lockForUpdate()->findOrFail($budget->getKey());
            $this->ensureEditableBudget($budget);
            if (! $budget->lines()->whereNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['budget' => 'Add at least one active budget line before approval.']);
            }
            $before = $budget->status;
            $budget->update(['status' => 'approved', 'approved_by_user_id' => $actor->getKey(), 'approved_at' => now(), 'updated_by_user_id' => $actor->getKey()]);
            $this->recordStatus($budget, $before, 'approved', $actor, 'Budget baseline approved');
            $this->audit->record('finance.budget_approved', $budget, ['status' => $before], ['status' => 'approved'], $actor);

            return $budget->refresh();
        }, 3);
    }

    public function createIncome(Event $event, array $data, User $actor): Income
    {
        /** @var Income */
        return $this->createEntry(Income::class, $event, $data, $actor, 'income');
    }

    public function createExpense(Event $event, array $data, User $actor): Expense
    {
        /** @var Expense */
        return $this->createEntry(Expense::class, $event, $data, $actor, 'expense');
    }

    public function updateIncome(Income $income, array $data, User $actor): Income
    {
        /** @var Income */
        return $this->updateEntry($income, $data, $actor, 'income');
    }

    public function updateExpense(Expense $expense, array $data, User $actor): Expense
    {
        /** @var Expense */
        return $this->updateEntry($expense, $data, $actor, 'expense');
    }

    public function postIncome(Income $income, User $actor): Income
    {
        /** @var Income */
        return $this->postEntry($income, $actor, 'income');
    }

    public function postExpense(Expense $expense, User $actor): Expense
    {
        /** @var Expense */
        return $this->postEntry($expense, $actor, 'expense');
    }

    public function voidIncome(Income $income, string $reason, User $actor): Income
    {
        /** @var Income */
        return $this->voidEntry($income, $reason, $actor, 'income');
    }

    public function voidExpense(Expense $expense, string $reason, User $actor): Expense
    {
        /** @var Expense */
        return $this->voidEntry($expense, $reason, $actor, 'expense');
    }

    public function recordPostedPaymentIncome(Event $event, array $data, User $actor): Income
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $existing = Income::query()->where('event_id', $event->getKey())
                ->where('source_type', 'payment')->where('source_id', $data['source_id'])->first();
            if ($existing) {
                return $existing;
            }

            $category = FinanceCategory::query()->where('slug', 'booking-payments')
                ->whereIn('direction', ['income', 'both'])->where('is_active', true)->firstOrFail();
            $entry = Income::query()->create([
                'event_id' => $event->getKey(), 'finance_category_id' => $category->getKey(),
                'client_id' => $data['client_id'], 'amount' => DecimalMath::normalize($data['amount']),
                'currency_code' => $event->currency_code, 'transaction_date' => $data['transaction_date'],
                'description' => $data['description'], 'status' => 'posted',
                'source_type' => 'payment', 'source_id' => $data['source_id'],
                'entered_by_user_id' => $actor->getKey(), 'approved_by_user_id' => $actor->getKey(),
                'posted_by_user_id' => $actor->getKey(), 'approved_at' => now(), 'posted_at' => now(),
            ]);
            $this->recordStatus($entry, 'draft', 'posted', $actor, 'Manual client payment posted');
            $this->audit->record('finance.income_posted_from_payment', $entry, [], $this->entrySnapshot($entry), $actor);

            return $entry;
        }, 3);
    }

    public function recordPostedPaymentRefund(Income $original, array $data, User $actor): Income
    {
        return DB::transaction(function () use ($original, $data, $actor) {
            $original = Income::query()->lockForUpdate()->findOrFail($original->getKey());
            if ($original->status !== 'posted' || $original->is_reversal) {
                throw ValidationException::withMessages(['payment' => 'The related posted payment income is unavailable for refund.']);
            }
            $existing = Income::query()->where('event_id', $original->event_id)
                ->where('source_type', 'payment_refund')->where('source_id', $data['source_id'])->first();
            if ($existing) {
                return $existing;
            }
            $prior = DecimalMath::sum(Income::query()->where('reversal_of_id', $original->getKey())
                ->where('status', 'posted')->pluck('amount'));
            if (DecimalMath::compare(DecimalMath::add($prior, $data['amount']), $original->amount) > 0) {
                throw ValidationException::withMessages(['amount' => 'The refund would exceed the posted payment income.']);
            }

            $adjustment = Income::query()->create([
                'event_id' => $original->event_id, 'finance_category_id' => $original->finance_category_id,
                'client_id' => $original->client_id, 'amount' => DecimalMath::normalize($data['amount']),
                'currency_code' => $original->currency_code, 'transaction_date' => $data['transaction_date'],
                'description' => $data['description'], 'status' => 'posted',
                'source_type' => 'payment_refund', 'source_id' => $data['source_id'],
                'is_reversal' => true, 'reversal_of_id' => $original->getKey(),
                'entered_by_user_id' => $actor->getKey(), 'approved_by_user_id' => $actor->getKey(),
                'posted_by_user_id' => $actor->getKey(), 'approved_at' => now(), 'posted_at' => now(),
                'metadata' => ['reason' => $data['reason']],
            ]);
            $this->recordStatus($adjustment, 'draft', 'posted', $actor, 'Manual payment refund posted');
            if (DecimalMath::compare(DecimalMath::add($prior, $adjustment->amount), $original->amount) === 0) {
                $original->update(['reversed_by_user_id' => $actor->getKey(), 'reversed_at' => now(), 'void_reason' => $data['reason']]);
            }
            $this->audit->record('finance.income_adjusted_for_refund', $adjustment, [], $this->entrySnapshot($adjustment), $actor);

            return $adjustment;
        }, 3);
    }

    public function summary(Event $event, bool $includeActuals = true): array
    {
        $lines = BudgetLine::query()->where('event_id', $event->getKey())->whereNull('archived_at')->with('category')->get();
        $incomes = $includeActuals
            ? Income::query()->where('event_id', $event->getKey())->where('status', 'posted')->with('category')->get()
            : collect();
        $expenses = $includeActuals
            ? Expense::query()->where('event_id', $event->getKey())->where('status', 'posted')->with('category')->get()
            : collect();
        $plannedIncome = DecimalMath::sum($lines->where('direction', 'income')->pluck('planned_amount'));
        $plannedExpense = DecimalMath::sum($lines->where('direction', 'expense')->pluck('planned_amount'));
        $actualIncome = $this->signedTotal($incomes);
        $actualExpense = $this->signedTotal($expenses);
        $categoryIds = $lines->pluck('finance_category_id')->merge($incomes->pluck('finance_category_id'))->merge($expenses->pluck('finance_category_id'))->filter()->unique();
        $categories = FinanceCategory::withTrashed()->whereIn('id', $categoryIds)->get()->keyBy('id');
        $byCategory = $categoryIds->map(function ($id) use ($lines, $incomes, $expenses, $categories) {
            $plannedIncome = DecimalMath::sum($lines->where('finance_category_id', $id)->where('direction', 'income')->pluck('planned_amount'));
            $plannedExpense = DecimalMath::sum($lines->where('finance_category_id', $id)->where('direction', 'expense')->pluck('planned_amount'));
            $actualIncome = $this->signedTotal($incomes->where('finance_category_id', $id));
            $actualExpense = $this->signedTotal($expenses->where('finance_category_id', $id));

            return [
                'category' => $categories->get($id)?->name ?? 'Uncategorized',
                'planned_income' => $plannedIncome, 'actual_income' => $actualIncome,
                'income_variance' => DecimalMath::subtract($actualIncome, $plannedIncome),
                'planned_expense' => $plannedExpense, 'actual_expense' => $actualExpense,
                'expense_variance' => DecimalMath::subtract($actualExpense, $plannedExpense),
            ];
        })->values();

        return [
            'currency_code' => $event->currency_code,
            'planned_income' => $plannedIncome, 'planned_expense' => $plannedExpense,
            'actual_income' => $actualIncome, 'actual_expense' => $actualExpense,
            'profit' => DecimalMath::subtract($actualIncome, $actualExpense),
            'categories' => $byCategory,
        ];
    }

    private function createEntry(string $modelClass, Event $event, array $data, User $actor, string $direction): Model
    {
        return DB::transaction(function () use ($modelClass, $event, $data, $actor, $direction) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            if ($event->isOperationallyReadOnly()) {
                throw ValidationException::withMessages(['event' => 'Completed, cancelled, or archived Events are read-only.']);
            }
            $this->ensureCategoryDirection((int) $data['finance_category_id'], $direction);
            $this->ensureEvidenceBelongsToEvent($event, $data['evidence_document_id'] ?? null);
            if (filled($data['source_type'] ?? null) xor filled($data['source_id'] ?? null)) {
                throw ValidationException::withMessages(['source_type' => 'Source type and source ID must be provided together.']);
            }
            if (filled($data['source_type'] ?? null)) {
                $existing = $modelClass::query()->where('event_id', $event->getKey())
                    ->where('source_type', $data['source_type'])->where('source_id', $data['source_id'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $attributes = [
                'event_id' => $event->getKey(), 'finance_category_id' => $data['finance_category_id'],
                'amount' => DecimalMath::normalize($data['amount']), 'currency_code' => $event->currency_code,
                'transaction_date' => $data['transaction_date'], 'description' => $data['description'],
                'status' => 'draft', 'source_type' => $data['source_type'] ?? null, 'source_id' => $data['source_id'] ?? null,
                'evidence_document_id' => $data['evidence_document_id'] ?? null, 'entered_by_user_id' => $actor->getKey(),
                'metadata' => $this->audit->sanitize($data['metadata'] ?? []) ?: null,
            ];
            $attributes[$direction === 'income' ? 'client_id' : 'vendor_id'] = $data[$direction === 'income' ? 'client_id' : 'vendor_id'] ?? null;
            $entry = $modelClass::query()->create($attributes);
            $this->audit->record("finance.{$direction}_created", $entry, [], $this->entrySnapshot($entry), $actor);

            return $entry->fresh(['category', $direction === 'income' ? 'client' : 'vendor']);
        }, 3);
    }

    private function updateEntry(Model $entry, array $data, User $actor, string $direction): Model
    {
        return DB::transaction(function () use ($entry, $data, $actor, $direction) {
            $entry = $entry->newQuery()->lockForUpdate()->findOrFail($entry->getKey());
            if ($entry->status !== 'draft' || $entry->is_reversal) {
                throw ValidationException::withMessages(['status' => 'Only ordinary draft entries may be edited. Posted and reversal entries are immutable.']);
            }
            $this->ensureCategoryDirection((int) $data['finance_category_id'], $direction);
            $this->ensureEvidenceBelongsToEvent($entry->event, $data['evidence_document_id'] ?? null);
            $before = $this->entrySnapshot($entry);
            $attributes = [
                'finance_category_id' => $data['finance_category_id'], 'amount' => DecimalMath::normalize($data['amount']),
                'transaction_date' => $data['transaction_date'], 'description' => $data['description'],
                'evidence_document_id' => $data['evidence_document_id'] ?? null,
            ];
            $attributes[$direction === 'income' ? 'client_id' : 'vendor_id'] = $data[$direction === 'income' ? 'client_id' : 'vendor_id'] ?? null;
            $entry->update($attributes);
            $this->audit->record("finance.{$direction}_updated", $entry, $before, $this->entrySnapshot($entry), $actor);

            return $entry->refresh();
        }, 3);
    }

    private function postEntry(Model $entry, User $actor, string $direction): Model
    {
        return DB::transaction(function () use ($entry, $actor, $direction) {
            $entry = $entry->newQuery()->lockForUpdate()->findOrFail($entry->getKey());
            if ($entry->status !== 'draft' || $entry->is_reversal) {
                throw ValidationException::withMessages(['status' => 'Only an ordinary draft entry may be posted.']);
            }
            $entry->update([
                'status' => 'posted', 'approved_by_user_id' => $actor->getKey(), 'approved_at' => now(),
                'posted_by_user_id' => $actor->getKey(), 'posted_at' => now(),
            ]);
            $this->recordStatus($entry, 'draft', 'posted', $actor, 'Financial entry posted');
            $this->audit->record("finance.{$direction}_posted", $entry, ['status' => 'draft'], ['status' => 'posted', 'amount' => $entry->amount], $actor);

            return $entry->refresh();
        }, 3);
    }

    private function voidEntry(Model $entry, string $reason, User $actor, string $direction): Model
    {
        return DB::transaction(function () use ($entry, $reason, $actor, $direction) {
            $entry = $entry->newQuery()->lockForUpdate()->findOrFail($entry->getKey());
            if ($entry->is_reversal || $entry->status === 'void' || $entry->reversed_at) {
                throw ValidationException::withMessages(['status' => 'This entry is already void or reversed.']);
            }
            if ($entry->status === 'draft') {
                $entry->update(['status' => 'void', 'voided_by_user_id' => $actor->getKey(), 'voided_at' => now(), 'void_reason' => $reason]);
                $this->recordStatus($entry, 'draft', 'void', $actor, $reason);
                $this->audit->record("finance.{$direction}_voided", $entry, ['status' => 'draft'], ['status' => 'void', 'reason' => $reason], $actor);

                return $entry->refresh();
            }
            if ($entry->status !== 'posted') {
                throw ValidationException::withMessages(['status' => 'Only draft or posted entries may be voided.']);
            }

            $attributes = $entry->only([
                'event_id', 'finance_category_id', 'client_id', 'vendor_id', 'amount', 'currency_code',
                'transaction_date', 'description', 'evidence_document_id',
            ]);
            $attributes = array_filter($attributes, fn ($value) => $value !== null);
            $attributes += [
                'description' => 'Reversal: '.$entry->description, 'status' => 'draft',
                'source_type' => 'reversal', 'source_id' => $entry->getKey(), 'is_reversal' => true,
                'reversal_of_id' => $entry->getKey(), 'entered_by_user_id' => $actor->getKey(),
                'metadata' => ['reason' => $reason],
            ];
            $reversal = $entry->newQuery()->create($attributes);
            $reversal->update([
                'status' => 'posted', 'approved_by_user_id' => $actor->getKey(), 'approved_at' => now(),
                'posted_by_user_id' => $actor->getKey(), 'posted_at' => now(),
            ]);
            $this->recordStatus($reversal, 'draft', 'posted', $actor, 'Reversal posted: '.$reason);
            $entry->update(['reversed_by_user_id' => $actor->getKey(), 'reversed_at' => now(), 'void_reason' => $reason]);
            $this->audit->record("finance.{$direction}_reversed", $entry, [], ['reason' => $reason, 'reversal_entry_id' => $reversal->getKey()], $actor);

            return $entry->refresh();
        }, 3);
    }

    private function signedTotal(Collection $entries): string
    {
        return DecimalMath::sum($entries->map(fn ($entry) => $entry->is_reversal ? '-'.$entry->amount : $entry->amount));
    }

    private function ensureEditableBudget(EventBudget $budget): void
    {
        if ($budget->status !== 'draft' || $budget->archived_at) {
            throw ValidationException::withMessages(['budget' => 'Only an active draft budget may be changed.']);
        }
    }

    private function ensureCategoryDirection(int $categoryId, string $direction): void
    {
        $category = FinanceCategory::query()->where('is_active', true)->findOrFail($categoryId);
        if (! in_array($category->direction, [$direction, 'both'], true)) {
            throw ValidationException::withMessages(['finance_category_id' => "The selected category cannot be used for {$direction} entries."]);
        }
    }

    private function ensureEvidenceBelongsToEvent(Event $event, ?int $documentId): void
    {
        if (! $documentId) {
            return;
        }
        $allowed = Document::query()->whereKey($documentId)->whereHas('links', fn ($links) => $links
            ->where('linkable_type', $event->getMorphClass())->where('linkable_id', $event->getKey()))->exists();
        if (! $allowed) {
            throw ValidationException::withMessages(['evidence_document_id' => 'Evidence must be a protected Document linked to this Event.']);
        }
    }

    private function recordStatus(Model $subject, string $from, string $to, User $actor, string $reason): void
    {
        StatusHistory::query()->create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'from_status' => $from, 'to_status' => $to, 'actor_type' => 'user',
            'actor_user_id' => $actor->getKey(), 'reason' => $reason, 'changed_at' => now(),
        ]);
    }

    private function lineSnapshot(BudgetLine $line): array
    {
        return $line->only(['event_id', 'finance_category_id', 'direction', 'description', 'planned_amount', 'currency_code']);
    }

    private function entrySnapshot(Model $entry): array
    {
        return $entry->only(['event_id', 'finance_category_id', 'amount', 'currency_code', 'transaction_date', 'description', 'status', 'source_type', 'source_id']);
    }
}
