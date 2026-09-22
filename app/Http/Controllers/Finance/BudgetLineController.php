<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBudgetLineRequest;
use App\Http\Requests\Finance\UpdateBudgetLineRequest;
use App\Models\BudgetLine;
use App\Models\Event;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BudgetLineController extends Controller
{
    public function store(StoreBudgetLineRequest $request, Event $event, FinanceService $finance): RedirectResponse
    {
        $budget = $event->budget()->firstOrFail();
        $finance->addBudgetLine($budget, $request->validated(), $request->user());

        return back()->with('status', 'Budget line added.');
    }

    public function update(UpdateBudgetLineRequest $request, Event $event, BudgetLine $line, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $line);
        $finance->updateBudgetLine($line, $request->validated(), $request->user());

        return back()->with('status', 'Budget line updated.');
    }

    public function archive(Request $request, Event $event, BudgetLine $line, FinanceService $finance): RedirectResponse
    {
        $this->assertNested($event, $line);
        Gate::authorize('update', $event->budget);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $finance->archiveBudgetLine($line, $data['reason'], $request->user());

        return back()->with('status', 'Budget line archived with history preserved.');
    }

    private function assertNested(Event $event, BudgetLine $line): void
    {
        abort_unless($line->event_id === $event->getKey(), 404);
    }
}
