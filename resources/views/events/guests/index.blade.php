<x-layouts.app :title="'Guests · '.$event->name" wide :breadcrumbs="[['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Guests']]">
    <x-ui.page-header title="Guests" :subtitle="$event->reference_number.' · '.$event->name">
        <x-slot:actions>
            @if(auth()->user()->hasPermission('guests.check-in'))<a class="btn btn-outline-primary" href="{{ route('events.guests.check-in.lookup',$event) }}">Front-desk check-in</a>@endif
            @can('create',[App\Models\Guest::class,$event])<a class="btn btn-primary" href="{{ route('events.guests.create',$event) }}">Add guest</a>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if($capacity['warning'])<div class="alert alert-warning" role="status"><strong>Capacity warning:</strong> {{ $capacity['confirmed'] }} confirmed attendees exceed {{ $capacity['source'] ?? 'the selected venue' }} capacity of {{ $capacity['capacity'] }}.</div>
    @elseif($capacity['capacity'] !== null)<div class="alert alert-info" role="status">Confirmed attendance: {{ $capacity['confirmed'] }} of {{ $capacity['capacity'] }} at {{ $capacity['source'] }}.</div>@endif

    <x-ui.filter-bar :action="route('events.guests.index',$event)">
        <div class="col-md-3"><x-ui.form.input name="q" label="Search" :value="$filters['q']??null" placeholder="Name, email, phone, reference"/></div>
        <div class="col-md-2"><x-ui.form.select name="rsvp_status" label="RSVP" :options="collect(['pending'=>'Pending','accepted'=>'Accepted','declined'=>'Declined','tentative'=>'Tentative'])" :value="$filters['rsvp_status']??null" placeholder="All"/></div>
        <div class="col-md-2"><x-ui.form.select name="invitation_status" label="Invitation" :options="collect(['not_invited'=>'Not invited','issued'=>'Issued','sent'=>'Sent','revoked'=>'Revoked'])" :value="$filters['invitation_status']??null" placeholder="All"/></div>
        <div class="col-md-2"><x-ui.form.select name="group_id" label="Group" :options="$groups->pluck('name','id')" :value="$filters['group_id']??null" placeholder="All"/></div>
        <div class="col-md-1"><x-ui.form.select name="vip" label="VIP" :options="collect(['1'=>'Yes','0'=>'No'])" :value="$filters['vip']??null" placeholder="All"/></div>
        <div class="col-md-2"><x-ui.form.select name="archived" label="Record state" :options="collect(['0'=>'Active','1'=>'Archived'])" :value="$filters['archived']??'0'"/></div>
    </x-ui.filter-bar>

    <section class="card mb-4"><div class="card-body p-0">
        <x-ui.data-table :columns="[['label'=>'Guest'],['label'=>'Group'],['label'=>'Invitation'],['label'=>'RSVP'],['label'=>'Seat'],['label'=>'Check-in'],['label'=>'Actions']]" caption="Event guest list" :empty="$guests->isEmpty()" empty-title="No Guests match these filters">
            @foreach($guests as $guest)<tr>
                <td><a class="fw-semibold" href="{{ route('events.guests.show',[$event,$guest]) }}">{{ $guest->display_name }}</a>@if($guest->is_vip) <span class="badge text-bg-warning">VIP</span>@endif @if($guest->archived_at)<span class="badge text-bg-secondary">Archived</span>@endif<span class="d-block small text-secondary">{{ $guest->email ?: $guest->phone ?: 'No contact details' }}</span></td>
                <td>{{ $guest->groupMembership?->group?->name ?? '—' }}</td>
                <td><x-ui.status-badge :status="$guest->invitation_status"/></td>
                <td><x-ui.status-badge :status="$guest->rsvp_status"/> @if($guest->confirmed_party_size)<span class="small">× {{ $guest->confirmed_party_size }}</span>@endif</td>
                <td>{{ $guest->seatAssignment ? $guest->seatAssignment->table_label.' / '.$guest->seatAssignment->seat_label : '—' }}</td>
                <td>{{ $guest->checkIn ? $guest->checkIn->checked_in_at->format('Y-m-d H:i') : 'Not checked in' }}</td>
                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('events.guests.show',[$event,$guest]) }}">Open</a></td>
            </tr>@endforeach
        </x-ui.data-table>
    </div></section>
    {{ $guests->links() }}

    @if(auth()->user()->hasPermission('guests.create'))
    <section class="card"><div class="card-body p-4"><h2 class="h4">Groups and families</h2>
        <div class="row g-4"><div class="col-lg-7"><x-ui.data-table :columns="[['label'=>'Name'],['label'=>'Type'],['label'=>'Members'],['label'=>'VIP']]" caption="Guest groups" :empty="$groups->isEmpty()" empty-title="No groups yet">@foreach($groups as $group)<tr><td>{{ $group->name }}</td><td>{{ str($group->type)->headline() }}</td><td>{{ $group->members_count }}</td><td>{{ $group->is_vip?'Yes':'No' }}</td></tr>@endforeach</x-ui.data-table></div>
        <div class="col-lg-5"><form method="POST" action="{{ route('events.guest-groups.store',$event) }}">@csrf<div class="row"><div class="col-md-7"><x-ui.form.input name="name" label="Group name" required/></div><div class="col-md-5"><x-ui.form.select name="type" label="Type" :options="collect(['family'=>'Family','household'=>'Household','company'=>'Company','party'=>'Party','other'=>'Other'])" value="family" required/></div></div><x-ui.form.textarea name="description" label="Description" rows="2"/><input type="hidden" name="is_vip" value="0"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="group_is_vip" name="is_vip" value="1"><label class="form-check-label" for="group_is_vip">VIP group</label></div><button class="btn btn-outline-primary">Create group</button></form></div></div>
    </div></section>
    @endif
</x-layouts.app>
