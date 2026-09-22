<x-layouts.app title="Invoices" :breadcrumbs="[['label' => 'Events', 'url' => route('events.index')], ['label' => $event->reference_number, 'url' => route('events.show', $event)], ['label' => 'Invoices']]" wide>
    <x-ui.page-header title="Invoices" :subtitle="$event->name.' · immutable issued snapshots reconciled with manual Payments'">
        <x-slot:actions>
            @can('create', [\App\Models\Invoice::class, $event])<a class="btn btn-primary" href="{{ route('events.invoices.create', $event) }}">Create Invoice</a>@endcan
        </x-slot:actions>
    </x-ui.page-header>
    <form class="card card-body mb-4" method="GET">
        <div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Number, subject, or client"></div><div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All</option>@foreach([...\App\Models\Invoice::STATUSES, 'overdue'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div></div>
    </form>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Invoice</th><th>Due</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th><th></th></tr></thead><tbody>
    @forelse($invoices as $invoice)<tr><td><strong>{{ $invoice->invoice_number ?? 'Draft #'.$invoice->id }}</strong><div class="small text-secondary">{{ $invoice->subject ?: $invoice->client_name }}</div></td><td>{{ $invoice->due_date->format('Y-m-d') }}</td><td><x-ui.status-badge :status="$invoice->displayStatus()"/></td><td class="text-end">{{ $invoice->currency_code }} {{ number_format((float) $invoice->total, 2) }}</td><td class="text-end">{{ $invoice->currency_code }} {{ number_format((float) $balances->balance($invoice), 2) }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('events.invoices.show', [$event, $invoice]) }}">Open</a></td></tr>@empty<tr><td colspan="6"><x-ui.empty-state title="No Invoices" message="Create a Draft only when this Event needs invoicing."/></td></tr>@endforelse
    </tbody></table></div></div><x-ui.pagination :paginator="$invoices"/>
</x-layouts.app>
