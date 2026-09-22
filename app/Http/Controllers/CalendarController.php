<?php

namespace App\Http\Controllers;

use App\Services\BranchScope;
use App\Services\CalendarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CalendarController extends Controller
{
    public function __invoke(Request $request, CalendarService $calendar, BranchScope $branches): View
    {
        Gate::authorize('calendar.view');
        $filters = $request->validate([
            'view' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', Rule::in(CalendarService::SOURCES)],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $view = $filters['view'] ?? 'monthly';
        $window = $calendar->window($view, $filters['date'] ?? null);
        $entries = $calendar->entries($request->user(), $window['start'], $window['end'], $filters['source'] ?? null, isset($filters['branch']) ? (int) $filters['branch'] : null);

        return view('calendar.index', compact('entries', 'filters', 'view', 'window') + [
            'branches' => $branches->optionsFor($request->user()),
            'sources' => CalendarService::SOURCES,
        ]);
    }
}
