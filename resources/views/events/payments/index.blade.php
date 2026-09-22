<x-layouts.app
    :title="'Payments · '.$event->name"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => 'Payments'],
    ]"
>
    <x-ui.page-header title="Manual payments" :subtitle="$event->reference_number.' · '.$event->name">
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}">Back to event</a>
            <a class="btn btn-outline-primary" href="{{ route('payments.due') }}">All payment dues</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="alert alert-info" role="status">
        Payments are recorded manually. Cash, bKash, bank, or another channel is free-text history only; this application does not process online payments or store card data.
    </div>

    <div class="row g-3 mb-4" aria-label="Payment summary">
        @foreach ([
            'Scheduled' => $summary['scheduled'], 'Outstanding due' => $summary['outstanding'],
            'Gross received' => $summary['received'], 'Refunded' => $summary['refunded'],
            'Net received' => $summary['net_received'], 'Unallocated advance' => $summary['unallocated'],
        ] as $label => $amount)
            <div class="col-6 col-lg-2"><section class="card h-100"><div class="card-body"><span class="small text-secondary d-block">{{ $label }}</span><strong>{{ number_format((float) $amount, 2) }} {{ $event->currency_code }}</strong></div></section></div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card mb-4" aria-labelledby="payment-history-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="payment-history-title">Payment history</h2>
                    <form class="row g-3 align-items-end mb-4" method="GET">
                        <div class="col-md-4"><x-ui.form.input name="q" label="Search" :value="$filters['q'] ?? ''"/></div>
                        <div class="col-md-3"><x-ui.form.select name="payment_type" label="Type" :options="collect(App\Models\Payment::TYPES)->mapWithKeys(fn($type) => [$type => str($type)->headline()])" :value="$filters['payment_type'] ?? ''" placeholder="All types"/></div>
                        <div class="col-md-2"><x-ui.form.input name="date_from" type="date" label="From" :value="$filters['date_from'] ?? ''"/></div>
                        <div class="col-md-2"><x-ui.form.input name="date_to" type="date" label="To" :value="$filters['date_to'] ?? ''"/></div>
                        <div class="col-md-1"><button class="btn btn-outline-primary w-100" type="submit">Go</button></div>
                    </form>
                    <x-ui.data-table
                        :columns="[['label' => 'Receipt'], ['label' => 'Received'], ['label' => 'Amount'], ['label' => 'Allocation'], ['label' => 'Action']]"
                        caption="Manual client payments"
                        :empty="$payments->isEmpty()"
                        empty-title="No payments recorded"
                    >
                        @foreach ($payments as $payment)
                            @php
                                $allocated = App\Support\DecimalMath::sum($payment->allocations->pluck('amount'));
                                $refunded = App\Support\DecimalMath::sum($payment->refunds->pluck('amount'));
                            @endphp
                            <tr>
                                <td><a href="{{ route('events.payments.receipt', [$event, $payment]) }}">{{ $payment->receipt_number }}</a><span class="d-block small text-secondary">{{ str($payment->payment_type)->headline() }} · {{ $payment->channel ?: 'Channel not noted' }}</span></td>
                                <td>{{ $payment->received_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}<span class="d-block small text-secondary">{{ $payment->receivedBy?->name ?? 'Historical user' }}</span></td>
                                <td>{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency_code }}@if(App\Support\DecimalMath::compare($refunded, '0') > 0)<span class="d-block small text-danger">Refunded {{ number_format((float) $refunded, 2) }}</span>@endif</td>
                                <td>{{ number_format((float) $allocated, 2) }}<span class="d-block small text-secondary">{{ $payment->allocations->count() }} target(s)</span></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('events.payments.receipt', [$event, $payment]) }}">Receipt / refund</a></td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>
                    <x-ui.pagination :paginator="$payments"/>
                </div>
            </section>

            <section class="card" aria-labelledby="schedules-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="schedules-title">Schedules and due tracking</h2>
                    <x-ui.data-table
                        :columns="[['label' => 'Due'], ['label' => 'Amount'], ['label' => 'Outstanding'], ['label' => 'Status'], ['label' => 'Action']]"
                        caption="Payment schedules"
                        :empty="$schedules->isEmpty()"
                        empty-title="No payment schedules"
                    >
                        @foreach ($schedules as $schedule)
                            <tr>
                                <td>{{ $schedule->label }}<span class="d-block small text-secondary">{{ $schedule->due_date->format('Y-m-d') }}</span></td>
                                <td>{{ number_format((float) $schedule->amount_due, 2) }} {{ $schedule->currency_code }}</td>
                                <td>{{ number_format((float) $paymentService->scheduleOutstanding($schedule), 2) }} {{ $schedule->currency_code }}</td>
                                <td><x-ui.status-badge :status="$schedule->status"/></td>
                                <td>
                                    @if(!in_array($schedule->status, ['paid', 'cancelled'], true))
                                        @can('schedule', [App\Models\Payment::class, $event])
                                            <form method="POST" action="{{ route('events.payment-schedules.cancel', [$event, $schedule]) }}">
                                                @csrf @method('PATCH')
                                                <label class="visually-hidden" for="reason-{{ $schedule->id }}">Cancellation reason</label>
                                                <input class="form-control form-control-sm mb-1" id="reason-{{ $schedule->id }}" name="reason" required placeholder="Reason">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                            </form>
                                        @endcan
                                    @else — @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>
                    <x-ui.pagination :paginator="$schedules"/>
                </div>
            </section>
        </div>

        <aside class="col-xl-4">
            @can('create', [App\Models\Payment::class, $event])
                <section class="card mb-4" aria-labelledby="record-payment-title">
                    <div class="card-body p-4">
                        <h2 class="h4" id="record-payment-title">Record and post payment</h2>
                        <p class="small text-secondary">Any amount not allocated below remains an advance/credit. Overpayment never makes due negative.</p>
                        <form method="POST" action="{{ route('events.payments.store', $event) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
                            <x-ui.form.select name="payment_type" label="Payment type" :options="collect(App\Models\Payment::TYPES)->mapWithKeys(fn($type) => [$type => str($type)->headline()])" required/>
                            <x-ui.form.input name="amount" type="number" step="0.0001" min="0.0001" label="Amount" required/>
                            <x-ui.form.input name="received_at" type="datetime-local" label="Received at" :value="old('received_at', now()->setTimezone($organizationTimezone)->format('Y-m-d\TH:i'))" required/>
                            <x-ui.form.select name="received_by_user_id" label="Received by" :options="$activeUsers->pluck('name', 'id')" :value="auth()->id()" required/>
                            <x-ui.form.input name="channel" label="Channel note" placeholder="Cash, bKash transfer, Bank transfer"/>
                            <x-ui.form.input name="external_reference" label="External reference"/>
                            <x-ui.form.textarea name="notes" label="Notes" rows="2"/>
                            @if($schedules->whereNotIn('status', ['paid', 'cancelled'])->isNotEmpty())
                                <fieldset class="mb-3">
                                    <legend class="h6">Optional schedule allocations</legend>
                                    @foreach($schedules->whereNotIn('status', ['paid', 'cancelled']) as $index => $schedule)
                                        <input type="hidden" name="allocations[{{ $index }}][payment_schedule_id]" value="{{ $schedule->id }}">
                                        <label class="form-label small" for="allocation-{{ $schedule->id }}">{{ $schedule->label }} (due {{ number_format((float) $paymentService->scheduleOutstanding($schedule), 2) }})</label>
                                        <input class="form-control mb-2" id="allocation-{{ $schedule->id }}" name="allocations[{{ $index }}][amount]" type="number" min="0.0001" step="0.0001">
                                    @endforeach
                                </fieldset>
                            @endif
                            @if($payableInvoices->isNotEmpty())
                                <fieldset class="mb-3">
                                    <legend class="h6">Optional Invoice allocations</legend>
                                    <p class="small text-secondary">An allocation cannot exceed the Invoice balance. Keep excess as unallocated credit.</p>
                                    @foreach($payableInvoices as $index => $row)
                                        @php($allocationIndex = 1000 + $index)
                                        <input type="hidden" name="allocations[{{ $allocationIndex }}][invoice_id]" value="{{ $row['invoice']->id }}">
                                        <label class="form-label small" for="invoice-allocation-{{ $row['invoice']->id }}">{{ $row['invoice']->invoice_number }} (balance {{ number_format((float) $row['balance'], 2) }})</label>
                                        <input class="form-control mb-2" id="invoice-allocation-{{ $row['invoice']->id }}" name="allocations[{{ $allocationIndex }}][amount]" type="number" min="0.0001" max="{{ $row['balance'] }}" step="0.0001">
                                    @endforeach
                                </fieldset>
                            @endif
                            <button class="btn btn-primary w-100" type="submit">Post manual payment</button>
                        </form>
                    </div>
                </section>
            @endcan

            @can('schedule', [App\Models\Payment::class, $event])
                <section class="card">
                    <div class="card-body p-4">
                        <h2 class="h4">Add payment schedule</h2>
                        <form method="POST" action="{{ route('events.payment-schedules.store', $event) }}">
                            @csrf
                            <x-ui.form.input name="label" label="Label" placeholder="Final balance" required/>
                            <x-ui.form.input name="amount_due" type="number" min="0.0001" step="0.0001" label="Amount due" required/>
                            <x-ui.form.input name="due_date" type="date" label="Due date" required/>
                            <x-ui.form.textarea name="notes" label="Notes" rows="2"/>
                            <button class="btn btn-outline-primary w-100" type="submit">Create schedule</button>
                        </form>
                    </div>
                </section>
            @endcan
        </aside>
    </div>
</x-layouts.app>
