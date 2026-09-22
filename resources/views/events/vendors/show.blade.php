<x-layouts.app :title="$assignment->vendor->display_name.' · '.$event->name" wide :breadcrumbs="[['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Vendors','url'=>route('events.vendors.index',$event)],['label'=>$assignment->vendor->display_name]]">
    <x-ui.page-header :title="$assignment->vendor->display_name" :subtitle="$assignment->category->name.' · '.$event->reference_number">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('events.vendors.index',$event) }}">Back to assignments</a></x-slot:actions>
    </x-ui.page-header>
    @if($assignment->availability_warning)<div class="alert alert-warning"><strong>Availability warning:</strong> {{ collect($assignment->availability_warning_details)->join(' ') }}</div>@endif

    <div class="row g-4"><div class="col-xl-8">
        <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Assignment</h2><dl class="row mb-0">
            <dt class="col-sm-4">Scope</dt><dd class="col-sm-8">{{ $assignment->scope }}</dd>
            <dt class="col-sm-4">Schedule</dt><dd class="col-sm-8">{{ $assignment->scheduled_starts_at->setTimezone($event->timezone)->format('Y-m-d H:i') }} – {{ $assignment->scheduled_ends_at->setTimezone($event->timezone)->format('Y-m-d H:i T') }}</dd>
            <dt class="col-sm-4">Quoted / approved</dt><dd class="col-sm-8">{{ $assignment->quoted_cost!==null ? $assignment->currency_code.' '.number_format((float)$assignment->quoted_cost,2) : '—' }} / {{ $assignment->approved_cost!==null ? $assignment->currency_code.' '.number_format((float)$assignment->approved_cost,2) : '—' }}</dd>
            <dt class="col-sm-4">Responsible manager</dt><dd class="col-sm-8">{{ $assignment->responsibleManager?->name??'Unassigned' }}</dd>
            <dt class="col-sm-4">Delivery</dt><dd class="col-sm-8">{{ str($assignment->delivery_status)->headline() }} · {{ $assignment->delivery_notes?:'No notes' }}</dd>
        </dl></div></section>

        @can('vendors.assign')
            <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Edit assignment</h2>
                <form method="POST" action="{{ route('events.vendors.update',[$event,$assignment]) }}">@csrf @method('PUT')
                    <input type="hidden" name="vendor_id" value="{{ $assignment->vendor_id }}">
                    <input type="hidden" name="vendor_category_id" value="{{ $assignment->vendor_category_id }}">
                    <div class="row g-3">
                        <div class="col-12"><x-ui.form.textarea name="scope" label="Scope of work" :value="$assignment->scope" rows="3" required/></div>
                        <div class="col-md-6"><x-ui.form.input name="scheduled_starts_at_local" label="Scheduled start" type="datetime-local" :value="$assignment->scheduled_starts_at->setTimezone($event->timezone)->format('Y-m-d\TH:i')" required/></div>
                        <div class="col-md-6"><x-ui.form.input name="scheduled_ends_at_local" label="Scheduled end" type="datetime-local" :value="$assignment->scheduled_ends_at->setTimezone($event->timezone)->format('Y-m-d\TH:i')" required/></div>
                        <div class="col-md-4"><x-ui.form.input name="quoted_cost" label="Quoted cost" type="number" step="0.0001" min="0" :value="$assignment->quoted_cost"/></div>
                        @can('vendors.approve-cost')<div class="col-md-4"><x-ui.form.input name="approved_cost" label="Approved cost" type="number" step="0.0001" min="0" :value="$assignment->approved_cost"/></div>@endcan
                        <div class="col-md-4"><x-ui.form.input name="currency_code" label="Currency" :value="$assignment->currency_code" required/></div>
                        <div class="col-md-6"><x-ui.form.select name="responsible_manager_user_id" label="Responsible manager" :options="$managers->pluck('name','id')" :value="$assignment->responsible_manager_user_id" placeholder="Unassigned"/></div>
                        <div class="col-md-6"><x-ui.form.select name="delivery_status" label="Delivery status" :options="collect(['pending','scheduled','in_progress','delivered','issue'])->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])" :value="$assignment->delivery_status" required/></div>
                        <div class="col-12"><x-ui.form.textarea name="delivery_notes" label="Delivery notes" :value="$assignment->delivery_notes" rows="2"/></div>
                    </div>
                    <button class="btn btn-outline-primary">Save assignment</button>
                </form>
            </div></section>
        @endcan

        <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Work orders</h2>
            <x-ui.data-table :columns="[['label'=>'Reference'],['label'=>'Work'],['label'=>'Due'],['label'=>'Status'],['label'=>'Update']]" caption="Vendor work orders" :empty="$assignment->workOrders->isEmpty()" empty-title="No work orders">
                @foreach($assignment->workOrders as $workOrder)<tr><td>{{ $workOrder->reference_number }}</td><td>{{ $workOrder->title }}<span class="d-block small text-secondary">{{ str($workOrder->instructions)->limit(80) }}</span></td><td>{{ $workOrder->due_at?->setTimezone($event->timezone)->format('Y-m-d H:i')??'—' }}</td><td><x-ui.status-badge :status="$workOrder->status"/></td><td>@can('update',$assignment)<form class="d-flex gap-2" method="POST" action="{{ route('events.vendors.work-orders.status',[$event,$assignment,$workOrder]) }}">@csrf @method('PATCH')<select class="form-select form-select-sm" name="status" aria-label="New work order status">@foreach(['issued','accepted','in_progress','completed','cancelled'] as $status)<option value="{{ $status }}">{{ str($status)->headline() }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Save</button></form>@endcan</td></tr>@endforeach
            </x-ui.data-table>
            @can('vendors.assign')<hr><form method="POST" action="{{ route('events.vendors.work-orders.store',[$event,$assignment]) }}">@csrf<div class="row g-3"><div class="col-md-6"><x-ui.form.input name="title" label="Work order title" required/></div><div class="col-md-6"><x-ui.form.input name="due_at" label="Due" type="datetime-local"/></div><div class="col-12"><x-ui.form.textarea name="instructions" label="Instructions" required/></div><div class="col-12"><x-ui.form.textarea name="deliverables" label="Deliverables"/></div></div><button class="btn btn-primary">Create work order</button></form>@endcan
        </div></section>

        <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Contracts</h2>
            @forelse($assignment->contracts as $contract)<p><a href="{{ route('documents.show',$contract->document) }}">{{ $contract->title }}</a> <x-ui.status-badge :status="$contract->status"/></p>@empty<p class="text-secondary">No contracts uploaded.</p>@endforelse
            @can('vendors.assign')<hr><form method="POST" enctype="multipart/form-data" action="{{ route('events.vendors.contracts.store',[$event,$assignment]) }}">@csrf<div class="row g-3"><div class="col-md-6"><x-ui.form.input name="title" label="Contract title" required/></div><div class="col-md-6"><x-ui.form.input name="contract_reference" label="Contract reference"/></div><div class="col-md-6"><x-ui.form.input name="effective_date" label="Effective date" type="date"/></div><div class="col-md-6"><x-ui.form.input name="expiry_date" label="Expiry date" type="date"/></div><div class="col-12"><label class="form-label" for="contract-file">Protected file</label><input class="form-control" id="contract-file" name="file" type="file" required></div><div class="col-12"><x-ui.form.textarea name="notes" label="Notes"/></div></div><button class="btn btn-primary">Upload contract</button></form>@endcan
        </div></section>

        <section class="card"><div class="card-body p-4"><h2 class="h4">Vendor invoices</h2><p class="text-secondary">These are protected invoice records only. They do not create Finance expenses.</p>
            <x-ui.data-table :columns="[['label'=>'Invoice'],['label'=>'Amount'],['label'=>'Status'],['label'=>'File']]" caption="Vendor invoice metadata" :empty="$assignment->invoices->isEmpty()" empty-title="No invoices">
                @foreach($assignment->invoices as $invoice)<tr><td>{{ $invoice->invoice_number?:'Unnumbered' }}<span class="d-block small text-secondary">{{ $invoice->invoice_date->format('Y-m-d') }}</span></td><td>{{ $invoice->currency_code }} {{ number_format((float)$invoice->amount,2) }}</td><td><x-ui.status-badge :status="$invoice->status"/></td><td><a href="{{ route('documents.show',$invoice->document) }}">View protected file</a></td></tr>@endforeach
            </x-ui.data-table>
            @can('uploadInvoice',$assignment)<hr><form method="POST" enctype="multipart/form-data" action="{{ route('events.vendors.invoices.store',[$event,$assignment]) }}">@csrf<div class="row g-3"><div class="col-md-6"><x-ui.form.input name="title" label="Document title" required/></div><div class="col-md-6"><x-ui.form.input name="invoice_number" label="Invoice number"/></div><div class="col-md-4"><x-ui.form.input name="invoice_date" label="Invoice date" type="date" required/></div><div class="col-md-4"><x-ui.form.input name="amount" label="Amount" type="number" step="0.0001" min="0" required/></div><div class="col-md-4"><x-ui.form.input name="currency_code" label="Currency" :value="$assignment->currency_code" required/></div><div class="col-12"><label class="form-label" for="invoice-file">Protected invoice file</label><input class="form-control" id="invoice-file" name="file" type="file" required></div><div class="col-12"><x-ui.form.textarea name="notes" label="Notes"/></div></div><button class="btn btn-primary">Upload invoice</button></form>@endcan
        </div></section>
    </div><aside class="col-xl-4">
        @can('update',$assignment)<section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Delivery update</h2><form method="POST" action="{{ route('events.vendors.delivery',[$event,$assignment]) }}">@csrf @method('PATCH')<x-ui.form.select name="delivery_status" label="Delivery status" :options="collect(['pending','scheduled','in_progress','delivered','issue'])->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])" :value="$assignment->delivery_status" required/><x-ui.form.textarea name="delivery_notes" label="Delivery notes" :value="$assignment->delivery_notes"/><button class="btn btn-outline-primary">Save delivery update</button></form></div></section>@endcan
        <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Assignment status</h2><p><x-ui.status-badge :status="$assignment->status"/></p>
            @can('update', $assignment)
                @if(! in_array($assignment->status, ['completed', 'cancelled'], true))
                    <form method="POST" action="{{ route('events.vendors.status',[$event,$assignment]) }}">@csrf @method('PATCH')<x-ui.form.select name="status" label="Move to" :options="collect($assignment->allowedStatusTransitions()[$assignment->status]??[])->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])" required/><x-ui.form.textarea name="reason" label="Reason (required for cancellation)"/><x-ui.form.textarea name="completion_notes" label="Completion notes (required when completing)"/><button class="btn btn-primary">Update status</button></form>
                @endif
            @endcan
        </div></section>
        <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Status history</h2>@forelse($assignment->statusHistory as $history)<p class="small mb-2">{{ $history->from_status?str($history->from_status)->headline().' → ':'' }}{{ str($history->to_status)->headline() }}<span class="d-block text-secondary">{{ $history->actor?->name??'System' }} · {{ $history->changed_at->format('Y-m-d H:i') }}</span></p>@empty<p class="text-secondary">No history.</p>@endforelse</div></section>
        @if($assignment->status === 'completed' && ! $assignment->rating)
            @can('rate', $assignment)
                <section class="card"><div class="card-body p-4"><h2 class="h4">Rate completed work</h2><form method="POST" action="{{ route('events.vendors.rating.store',[$event,$assignment]) }}">@csrf<x-ui.form.select name="score" label="Score" :options="collect([1,2,3,4,5])->mapWithKeys(fn($v)=>[$v=>$v.' / 5'])" required/><x-ui.form.textarea name="comments" label="Reviewer comments"/><button class="btn btn-primary">Save rating</button></form></div></section>
            @endcan
        @elseif($assignment->rating)
            <section class="card"><div class="card-body p-4"><h2 class="h4">Rating</h2><p class="display-6">{{ $assignment->rating->score }}/5</p><p>{{ $assignment->rating->comments }}</p><small class="text-secondary">Reviewed by {{ $assignment->rating->reviewer?->name??'Former user' }}</small></div></section>
        @endif
    </aside></div>
</x-layouts.app>
