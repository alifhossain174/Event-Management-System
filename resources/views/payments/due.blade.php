<x-layouts.app title="Payment dues" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Payment dues']]">
    <x-ui.page-header title="Payment schedules and dues" subtitle="Authorized enabled Events only"/>
    <section class="card"><div class="card-body p-4">
        <form class="row g-3 align-items-end mb-4" method="GET">
            <div class="col-md-5"><x-ui.form.input name="q" label="Search event, client, or schedule" :value="$filters['q'] ?? ''"/></div>
            <div class="col-md-3"><x-ui.form.select name="status" label="Status" :options="collect(App\Models\PaymentSchedule::STATUSES)->mapWithKeys(fn($status) => [$status => str($status)->headline()])" :value="$filters['status'] ?? ''" placeholder="All statuses"/></div>
            <div class="col-md-2"><x-ui.form.select name="due" label="Due window" :options="['overdue' => 'Overdue', 'today' => 'Today', 'upcoming' => 'Upcoming']" :value="$filters['due'] ?? ''" placeholder="Any date"/></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Apply filters</button></div>
        </form>
        <x-ui.data-table :columns="[['label' => 'Due'], ['label' => 'Event'], ['label' => 'Client'], ['label' => 'Outstanding'], ['label' => 'Status']]" caption="Payment due list" :empty="$schedules->isEmpty()" empty-title="No payment dues match">
            @foreach($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->due_date->format('Y-m-d') }}<span class="d-block small text-secondary">{{ $schedule->label }}</span></td>
                    <td><a href="{{ route('events.payments.index', $schedule->event) }}">{{ $schedule->event->reference_number }} · {{ $schedule->event->name }}</a></td>
                    <td><a href="{{ route('clients.payments', $schedule->client) }}">{{ $schedule->client->display_name }}</a></td>
                    <td>{{ number_format((float) $paymentService->scheduleOutstanding($schedule), 2) }} {{ $schedule->currency_code }}</td>
                    <td><x-ui.status-badge :status="$schedule->status"/></td>
                </tr>
            @endforeach
        </x-ui.data-table>
        <x-ui.pagination :paginator="$schedules"/>
    </div></section>
</x-layouts.app>
