<x-layouts.app title="Login history" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Audit history', 'url' => route('audit.index')], ['label' => 'Login history']]">
    <x-ui.page-header title="Login history" subtitle="Successful and failed authentication attempts without credential data.">
        <x-slot:actions><a class="btn btn-outline-primary" href="{{ route('audit.index') }}">Action audit</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.filter-bar :action="route('audit.logins.index')" :clear-url="route('audit.logins.index')">
        <div class="col-12 col-lg-5"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Email or IP address"></div>
        <div class="col-12 col-md-4 col-lg-3"><label class="form-label" for="result">Result</label><select class="form-select" id="result" name="result"><option value="">All results</option><option value="success" @selected(($filters['result'] ?? '') === 'success')>Success</option><option value="failure" @selected(($filters['result'] ?? '') === 'failure')>Failure</option></select></div>
        <div class="col-6 col-md-4 col-lg-2"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="col-6 col-md-4 col-lg-2"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    </x-ui.filter-bar>
    <x-ui.data-table :columns="[['label' => 'When'], ['label' => 'Account'], ['label' => 'Result'], ['label' => 'IP'], ['label' => 'Reason'], ['label' => 'Actions', 'class' => 'text-end']]" caption="Authentication attempts" :empty="$histories->isEmpty()" empty-title="No login attempts match these filters">
        @foreach($histories as $history)<tr>
            <td class="text-nowrap">{{ $history->attempted_at->format('Y-m-d H:i:s T') }}</td><td>{{ $history->email }}</td>
            <td><x-ui.status-badge :status="$history->successful ? 'active' : 'inactive'">{{ $history->successful ? 'Success' : 'Failure' }}</x-ui.status-badge></td>
            <td>{{ $history->ip_address ?: '—' }}</td><td>{{ $history->failure_reason ?: '—' }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('audit.logins.show', $history) }}">View</a></td>
        </tr>@endforeach
    </x-ui.data-table>
    <x-ui.pagination :paginator="$histories"/>
</x-layouts.app>
