<x-layouts.app
    :title="'Payments · '.$client->display_name"
    wide
    :breadcrumbs="[
        ['label' => 'Clients', 'url' => route('clients.index')],
        ['label' => $client->display_name, 'url' => route('clients.show', $client)],
        ['label' => 'Payments'],
    ]"
>
    <x-ui.page-header title="Client payment history" :subtitle="$client->display_name">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('clients.show', $client) }}">Back to client</a></x-slot:actions>
    </x-ui.page-header>
    <div class="row g-3 mb-4">
        @foreach(['Outstanding due' => $summary['outstanding'], 'Gross received' => $summary['received'], 'Refunded' => $summary['refunded'], 'Net received' => $summary['net_received'], 'Unallocated advance' => $summary['unallocated']] as $label => $amount)
            <div class="col-6 col-lg"><section class="card h-100"><div class="card-body"><span class="small text-secondary d-block">{{ $label }}</span><strong>{{ number_format((float) $amount, 2) }}</strong></div></section></div>
        @endforeach
    </div>
    <section class="card mb-4"><div class="card-body p-4">
        <h2 class="h4">Payments</h2>
        <x-ui.data-table :columns="[['label' => 'Receipt'], ['label' => 'Event'], ['label' => 'Received'], ['label' => 'Amount']]" caption="Client payments" :empty="$payments->isEmpty()" empty-title="No enabled-event payments">
            @foreach($payments as $payment)
                <tr><td><a href="{{ route('events.payments.receipt', [$payment->event, $payment]) }}">{{ $payment->receipt_number }}</a></td><td>{{ $payment->event->name }}</td><td>{{ $payment->received_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</td><td>{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency_code }}</td></tr>
            @endforeach
        </x-ui.data-table>
        <x-ui.pagination :paginator="$payments"/>
    </div></section>
    <section class="card mb-4"><div class="card-body p-4">
        <h2 class="h4">Payment schedules</h2>
        <x-ui.data-table :columns="[['label' => 'Due'], ['label' => 'Event'], ['label' => 'Amount'], ['label' => 'Status']]" caption="Client payment schedules" :empty="$schedules->isEmpty()" empty-title="No enabled-event schedules">
            @foreach($schedules as $schedule)
                <tr><td>{{ $schedule->due_date->format('Y-m-d') }} · {{ $schedule->label }}</td><td>{{ $schedule->event->name }}</td><td>{{ number_format((float) $schedule->amount_due, 2) }} {{ $schedule->currency_code }}</td><td><x-ui.status-badge :status="$schedule->status"/></td></tr>
            @endforeach
        </x-ui.data-table>
        <x-ui.pagination :paginator="$schedules"/>
    </div></section>
    <section class="card"><div class="card-body p-4">
        <h2 class="h4">Refunds</h2>
        <x-ui.data-table :columns="[['label' => 'Refund'], ['label' => 'Payment'], ['label' => 'Amount'], ['label' => 'Reason']]" caption="Client refunds" :empty="$refunds->isEmpty()" empty-title="No enabled-event refunds">
            @foreach($refunds as $refund)
                <tr><td>{{ $refund->refund_number }}</td><td>{{ $refund->payment->receipt_number }}</td><td>{{ number_format((float) $refund->amount, 2) }} {{ $refund->currency_code }}</td><td>{{ $refund->reason }}</td></tr>
            @endforeach
        </x-ui.data-table>
        <x-ui.pagination :paginator="$refunds"/>
    </div></section>
</x-layouts.app>
