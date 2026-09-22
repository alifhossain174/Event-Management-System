<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceEntryRequest;
use App\Http\Requests\Finance\VoidFinanceEntryRequest;
use App\Models\Event;
use App\Models\Expense;
use App\Models\Income;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class FinanceEntryController extends Controller
{
    public function storeIncome(FinanceEntryRequest $request, Event $event, FinanceService $finance): RedirectResponse
    {
        $finance->createIncome($event, $request->financeAttributes(), $request->user());

        return back()->with('status', 'Draft income recorded.');
    }

    public function storeExpense(FinanceEntryRequest $request, Event $event, FinanceService $finance): RedirectResponse
    {
        $finance->createExpense($event, $request->financeAttributes(), $request->user());

        return back()->with('status', 'Draft expense recorded.');
    }

    public function updateIncome(FinanceEntryRequest $request, Event $event, Income $income, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $income);
        $finance->updateIncome($income, $request->financeAttributes(), $request->user());

        return back()->with('status', 'Draft income updated.');
    }

    public function updateExpense(FinanceEntryRequest $request, Event $event, Expense $expense, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $expense);
        $finance->updateExpense($expense, $request->financeAttributes(), $request->user());

        return back()->with('status', 'Draft expense updated.');
    }

    public function postIncome(Request $request, Event $event, Income $income, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $income);
        Gate::authorize('post', $income);
        $finance->postIncome($income, $request->user());

        return back()->with('status', 'Income posted.');
    }

    public function postExpense(Request $request, Event $event, Expense $expense, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $expense);
        Gate::authorize('post', $expense);
        $finance->postExpense($expense, $request->user());

        return back()->with('status', 'Expense posted.');
    }

    public function voidIncome(VoidFinanceEntryRequest $request, Event $event, Income $income, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $income);
        $finance->voidIncome($income, $request->validated('reason'), $request->user());

        return back()->with('status', 'Income void/reversal recorded.');
    }

    public function voidExpense(VoidFinanceEntryRequest $request, Event $event, Expense $expense, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $expense);
        $finance->voidExpense($expense, $request->validated('reason'), $request->user());

        return back()->with('status', 'Expense void/reversal recorded.');
    }

    private function assertNested(Event $event, Income|Expense $entry): void
    {
        abort_unless($entry->event_id === $event->getKey(), 404);
    }
}
