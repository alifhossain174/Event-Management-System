<x-layouts.app
    :title="$payment->receipt_number"
    :breadcrumbs="[
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => 'Payments', 'url' => route('events.payments.index', $event)],
        ['label' => $payment->receipt_number],
    ]"
>
    <x-ui.page-header title="Payment receipt" :subtitle="$payment->receipt_number">
        <x-slot:actions>
            <button class="btn btn-outline-primary" type="button" onclick="window.print()">Print receipt</button>
            <a class="btn btn-outline-secondary" href="{{ route('events.payments.index', $event) }}">Back</a>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="card mb-4" aria-labelledby="receipt-details-title">
        <div class="card-body p-4">
            <h2 class="h4" id="receipt-details-title">Receipt details</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Client</dt><dd class="col-sm-8">{{ $payment->client->display_name }}</dd>
                <dt class="col-sm-4">Event</dt><dd class="col-sm-8">{{ $event->reference_number }} · {{ $event->name }}</dd>
                <dt class="col-sm-4">Received</dt><dd class="col-sm-8">{{ $payment->received_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</dd>
                <dt class="col-sm-4">Received by</dt><dd class="col-sm-8">{{ $payment->receivedBy?->name ?? 'Historical user' }}</dd>
                <dt class="col-sm-4">Amount</dt><dd class="col-sm-8">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency_code }}</dd>
                <dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ str($payment->payment_type)->headline() }}</dd>
                <dt class="col-sm-4">Channel / reference</dt><dd class="col-sm-8">{{ $payment->channel ?: '—' }} @if($payment->external_reference) · {{ $payment->external_reference }} @endif</dd>
                <dt class="col-sm-4">Notes</dt><dd class="col-sm-8">{{ $payment->notes ?: '—' }}</dd>
                <dt class="col-sm-4">Refundable</dt><dd class="col-sm-8">{{ number_format((float) $refundable, 2) }} {{ $payment->currency_code }}</dd>
            </dl>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="card mb-4"><div class="card-body p-4">
                <h2 class="h4">Allocations</h2>
                <x-ui.data-table :columns="[['label' => 'Target'], ['label' => 'Amount'], ['label' => 'Refundable']]" caption="Payment allocations" :empty="$payment->allocations->isEmpty()" empty-title="Unallocated advance">
                    @foreach($payment->allocations as $allocation)
                        <tr><td>{{ $allocation->schedule?->label ?? ('Invoice #'.$allocation->invoice_id) }}</td><td>{{ number_format((float) $allocation->amount, 2) }}</td><td>{{ number_format((float) app(App\Services\PaymentService::class)->allocationRefundable($allocation), 2) }}</td></tr>
                    @endforeach
                </x-ui.data-table>
            </div></section>
            <section class="card"><div class="card-body p-4">
                <h2 class="h4">Refund history</h2>
                <x-ui.data-table :columns="[['label' => 'Refund'], ['label' => 'Amount'], ['label' => 'Reason']]" caption="Refund history" :empty="$payment->refunds->isEmpty()" empty-title="No refunds">
                    @foreach($payment->refunds as $refund)
                        <tr><td>{{ $refund->refund_number }}<span class="d-block small text-secondary">{{ $refund->refunded_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</span></td><td>{{ number_format((float) $refund->amount, 2) }} {{ $refund->currency_code }}</td><td>{{ $refund->reason }}</td></tr>
                    @endforeach
                </x-ui.data-table>
            </div></section>
        </div>
        <aside class="col-lg-5">
            @can('refund', $payment)
                @if(App\Support\DecimalMath::compare($refundable, '0') > 0)
                    <section class="card border-warning"><div class="card-body p-4">
                        <h2 class="h4">Record refund or correction</h2>
                        <p class="small text-secondary">Posted history is never edited. To correct a payment, refund the affected amount and post a replacement payment.</p>
                        <form method="POST" action="{{ route('events.payments.refunds.store', [$event, $payment]) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
                            <x-ui.form.input name="amount" type="number" min="0.0001" step="0.0001" label="Refund amount" required/>
                            <x-ui.form.input name="refunded_at" type="datetime-local" label="Refunded at" :value="old('refunded_at', now()->setTimezone($organizationTimezone)->format('Y-m-d\TH:i'))" required/>
                            <x-ui.form.select name="payment_allocation_id" label="Refund source" :options="$payment->allocations->mapWithKeys(fn($allocation) => [$allocation->id => $allocation->schedule?->label ?? ('Invoice #'.$allocation->invoice_id)])" :placeholder="'Unallocated advance ('.$unallocatedRefundable.' available)'"/>
                            <x-ui.form.input name="channel" label="Channel note"/>
                            <x-ui.form.input name="external_reference" label="External reference"/>
                            <x-ui.form.textarea name="reason" label="Reason" rows="3" required/>
                            <button class="btn btn-warning w-100" type="submit">Post refund</button>
                        </form>
                    </div></section>
                @endif
            @endcan
        </aside>
    </div>
</x-layouts.app>
