<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class LoginHistoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LoginHistory::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'in:success,failure'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $histories = LoginHistory::query()
            ->with('user')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(function ($query) use ($q) {
                $query->where('email', 'like', "%{$q}%")->orWhere('ip_address', 'like', "%{$q}%");
            }))
            ->when(($filters['result'] ?? null) === 'success', fn ($query) => $query->where('successful', true))
            ->when(($filters['result'] ?? null) === 'failure', fn ($query) => $query->where('successful', false))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('attempted_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('attempted_at', '<=', $to))
            ->orderByDesc('attempted_at')
            ->paginate(25)
            ->withQueryString();

        return view('audit.logins', compact('histories', 'filters'));
    }

    public function show(LoginHistory $loginHistory): View
    {
        Gate::authorize('view', $loginHistory);

        return view('audit.login-show', ['history' => $loginHistory->load('user')]);
    }
}
