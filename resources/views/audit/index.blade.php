<x-layouts.app title="Audit history" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Audit history']]">
    <x-ui.page-header title="Audit history" subtitle="Append-only records of sensitive application actions.">
        <x-slot:actions><a class="btn btn-outline-primary" href="{{ route('audit.logins.index') }}">Login history</a></x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('audit.index')" :clear-url="route('audit.index')">
        <div class="col-12 col-lg-4"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Action, entity, or entity ID"></div>
        <div class="col-12 col-md-4 col-lg-3"><label class="form-label" for="action">Action</label><select class="form-select" id="action" name="action"><option value="">All actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></div>
        <div class="col-6 col-md-4 col-lg-2"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="col-6 col-md-4 col-lg-2"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    </x-ui.filter-bar>

    <x-ui.data-table :columns="[['label' => 'When'], ['label' => 'Action'], ['label' => 'Actor'], ['label' => 'Entity'], ['label' => 'IP'], ['label' => 'Actions', 'class' => 'text-end']]" caption="Sensitive action audit entries" :empty="$logs->isEmpty()" empty-title="No audit entries match these filters">
        @foreach($logs as $log)
            <tr>
                <td class="text-nowrap">{{ $log->occurred_at->format('Y-m-d H:i:s T') }}</td>
                <td><code>{{ $log->action }}</code></td>
                <td>{{ $log->actor?->name ?? 'System' }}</td>
                <td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                <td>{{ $log->ip_address ?: '—' }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('audit.show', $log) }}">View</a></td>
            </tr>
        @endforeach
    </x-ui.data-table>
    <x-ui.pagination :paginator="$logs"/>
</x-layouts.app>
