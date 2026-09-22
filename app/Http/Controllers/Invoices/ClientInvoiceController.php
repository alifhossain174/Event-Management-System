<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\InvoiceBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClientInvoiceController extends Controller
{
    public function __invoke(Request $request, Client $client, InvoiceBalanceService $balances): View
    {
        Gate::authorize('view', $client);
        abort_unless($request->user()->hasPermission('invoices.view'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', 'string', 'max:24']]);
        $invoices = $client->invoices()->with('event')->whereHas('event.moduleSettings', fn (Builder $settings) => $settings
            ->where('module_key', 'invoices')->where('is_enabled', true))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $query) => $query
                ->where('invoice_number', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $status === 'overdue'
                ? $query->whereIn('status', ['issued', 'partially_paid'])->whereDate('due_date', '<', today())
                : $query->where('status', $status))
            ->paginate(15)->withQueryString();

        return view('clients.invoices', compact('client', 'invoices', 'filters', 'balances'));
    }
}
