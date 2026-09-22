<x-layouts.app :title="'Vendors · '.$event->name" wide :breadcrumbs="[['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Vendors']]">
    <x-ui.page-header title="Vendor assignments" :subtitle="$event->reference_number.' · '.$event->name">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('events.show',$event) }}">Back to event</a></x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('events.vendors.index',$event)">
        <div class="col-md-4"><x-ui.form.input name="q" label="Search" :value="$filters['q']??''"/></div>
        <div class="col-md-3"><x-ui.form.select name="status" label="Status" :options="collect(['draft','approved','in_progress','completed','cancelled'])->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])" :value="$filters['status']??''" placeholder="All statuses"/></div>
        <div class="col-md-3"><x-ui.form.select name="vendor" label="Vendor" :options="$vendors->pluck('display_name','id')" :value="$filters['vendor']??''" placeholder="All vendors"/></div>
    </x-ui.filter-bar>

    <section class="card mb-4"><div class="card-body p-4">
        <x-ui.data-table :columns="[['label'=>'Vendor'],['label'=>'Service'],['label'=>'Schedule'],['label'=>'Cost'],['label'=>'Status'],['label'=>'Action']]" caption="Event vendor assignments" :empty="$assignments->isEmpty()" empty-title="No vendor assignments">
            @foreach($assignments as $assignment)
                <tr>
                    <td>{{ $assignment->vendor->display_name }}</td>
                    <td>{{ $assignment->category->name }}<span class="d-block small text-secondary">{{ str($assignment->scope)->limit(70) }}</span></td>
                    <td>{{ $assignment->scheduled_starts_at->setTimezone($event->timezone)->format('Y-m-d H:i') }}<span class="d-block small text-secondary">to {{ $assignment->scheduled_ends_at->setTimezone($event->timezone)->format('Y-m-d H:i T') }}</span></td>
                    <td>{{ $assignment->approved_cost!==null ? $assignment->currency_code.' '.number_format((float)$assignment->approved_cost,2) : 'Not approved' }}</td>
                    <td><x-ui.status-badge :status="$assignment->status"/> @if($assignment->availability_warning)<span class="badge text-bg-warning">Conflict warning</span>@endif</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('events.vendors.show',[$event,$assignment]) }}">Open</a></td>
                </tr>
            @endforeach
        </x-ui.data-table>
        {{ $assignments->links() }}
    </div></section>

    @can('create', App\Models\VendorAssignment::class)
        <section class="card"><div class="card-body p-4"><h2 class="h4">Assign an available vendor</h2>
            <p class="text-secondary">An archived vendor cannot receive a new assignment. Overlaps follow the vendor's warn/block availability policy.</p>
            <form method="POST" action="{{ route('events.vendors.store',$event) }}">@csrf
                <div class="row g-3">
                    <div class="col-md-6"><x-ui.form.select name="vendor_id" label="Vendor" :options="$vendors->pluck('display_name','id')" required/></div>
                    <div class="col-md-6"><x-ui.form.select name="vendor_category_id" label="Service category" :options="$categories->pluck('name','id')" required/></div>
                    <div class="col-12"><x-ui.form.textarea name="scope" label="Scope of work" rows="3" required/></div>
                    <div class="col-md-6"><x-ui.form.input name="scheduled_starts_at_local" label="Scheduled start" type="datetime-local" :value="$event->starts_at->setTimezone($event->timezone)->format('Y-m-d\TH:i')" required/></div>
                    <div class="col-md-6"><x-ui.form.input name="scheduled_ends_at_local" label="Scheduled end" type="datetime-local" :value="$event->ends_at->setTimezone($event->timezone)->format('Y-m-d\TH:i')" required/></div>
                    <div class="col-md-4"><x-ui.form.input name="quoted_cost" label="Quoted cost" type="number" step="0.0001" min="0"/></div>
                    @can('vendors.approve-cost')<div class="col-md-4"><x-ui.form.input name="approved_cost" label="Approved cost" type="number" step="0.0001" min="0"/></div>@endcan
                    <div class="col-md-4"><x-ui.form.input name="currency_code" label="Currency" :value="$currency" required/></div>
                    <div class="col-md-6"><x-ui.form.select name="responsible_manager_user_id" label="Responsible manager" :options="$managers->pluck('name','id')" placeholder="Unassigned"/></div>
                    <div class="col-md-6"><x-ui.form.select name="delivery_status" label="Delivery status" :options="collect(['pending','scheduled','in_progress','delivered','issue'])->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])" value="pending" required/></div>
                    <div class="col-12"><x-ui.form.textarea name="delivery_notes" label="Delivery notes" rows="2"/></div>
                </div>
                <button class="btn btn-primary" type="submit">Create assignment</button>
            </form>
        </div></section>
    @endcan
</x-layouts.app>
