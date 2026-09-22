<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventBudget;
use App\Models\Expense;
use App\Models\FinanceCategory;
use App\Models\Income;
use App\Models\Vendor;
use App\Services\BranchScope;
use App\Services\FinanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class EventBudgetController extends Controller
{
    public function index(Request $request, Event $event, FinanceService $finance, BranchScope $branches): View
    {
        Gate::authorize('viewModule', [$event, 'budget']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', Rule::in(['income', 'expense'])],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'void'])],
            'finance_category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $budget = $event->budget;
        if ($budget) {
            Gate::authorize('view', $budget);
        } elseif (! $request->user()->hasPermission('budget.view')) {
            abort(403);
        }
        $lines = $event->budgetLines()->with('category')->whereNull('archived_at')
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where('description', 'like', "%{$q}%"))
            ->when($filters['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($filters['finance_category_id'] ?? null, fn (Builder $query, $id) => $query->where('finance_category_id', $id))
            ->orderBy('sort_order')->orderBy('id')->paginate(15, ['*'], 'lines_page')->withQueryString();
        $entryFilter = function (Builder $query) use ($filters): Builder {
            return $query->with(['category', 'enteredBy', 'evidenceDocument'])
                ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where('description', 'like', "%{$q}%"))
                ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['finance_category_id'] ?? null, fn (Builder $query, $id) => $query->where('finance_category_id', $id))
                ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('transaction_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate('transaction_date', '<=', $date))
                ->orderByDesc('transaction_date')->orderByDesc('id');
        };
        $incomes = $request->user()->hasPermission('finance.view') && ($filters['direction'] ?? null) !== 'expense'
            ? $entryFilter(Income::query()->where('event_id', $event->getKey()))->paginate(10, ['*'], 'income_page')->withQueryString()
            : null;
        $expenses = $request->user()->hasPermission('finance.view') && ($filters['direction'] ?? null) !== 'income'
            ? $entryFilter(Expense::query()->where('event_id', $event->getKey()))->paginate(10, ['*'], 'expense_page')->withQueryString()
            : null;
        $documents = Document::query()->whereHas('links', fn (Builder $links) => $links
            ->where('linkable_type', $event->getMorphClass())->where('linkable_id', $event->getKey()))
            ->where('status', 'active')->orderBy('title')->get();

        return view('events.budget.index', [
            'event' => $event, 'budget' => $budget, 'lines' => $lines, 'incomes' => $incomes, 'expenses' => $expenses,
            'filters' => $filters, 'summary' => $finance->summary($event, $request->user()->hasPermission('finance.view')),
            'incomeCategories' => FinanceCategory::query()->where('is_active', true)->whereIn('direction', ['income', 'both'])->orderBy('sort_order')->orderBy('name')->get(),
            'expenseCategories' => FinanceCategory::query()->where('is_active', true)->whereIn('direction', ['expense', 'both'])->orderBy('sort_order')->orderBy('name')->get(),
            'clients' => $branches->apply(Client::query()->where('status', 'active'), $request->user())->orderBy('display_name')->get(),
            'vendors' => $branches->apply(Vendor::query()->where('status', 'active'), $request->user())->orderBy('display_name')->get(),
            'documents' => $documents,
        ]);
    }

    public function store(Request $request, Event $event, FinanceService $finance): RedirectResponse
    {
        Gate::authorize('create', [EventBudget::class, $event]);
        $finance->createBudget($event, $request->user());

        return back()->with('status', 'Event budget created. Template suggestions were copied as editable planning lines where available.');
    }

    public function approve(Request $request, Event $event, FinanceService $finance): RedirectResponse
    {
        $budget = $event->budget()->firstOrFail();
        Gate::authorize('approve', $budget);
        $finance->approveBudget($budget, $request->user());

        return back()->with('status', 'Budget baseline approved and locked.');
    }
}
