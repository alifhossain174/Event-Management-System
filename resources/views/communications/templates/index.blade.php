<x-layouts.app title="Message templates" wide :breadcrumbs="[['label'=>'Overview','url'=>route('home')],['label'=>'Message templates']]">
    <x-ui.page-header title="Message templates" subtitle="Reusable snapshots for synchronous operational or consent-based marketing messages.">
        <x-slot:actions><a class="btn btn-primary" href="{{ route('message-templates.create') }}">Create template</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.filter-bar :action="route('message-templates.index')" :clear-url="route('message-templates.index')">
        <div class="col-12 col-md-6"><x-ui.form.input name="q" label="Search" :value="$filters['q'] ?? ''"/></div>
        <div class="col-12 col-md-4"><x-ui.form.select name="channel" label="Channel" :options="['email'=>'Email','sms'=>'SMS','whatsapp'=>'WhatsApp']" :value="$filters['channel'] ?? ''" placeholder="All channels"/></div>
    </x-ui.filter-bar>
    <x-ui.data-table :columns="[['label'=>'Template'],['label'=>'Channel'],['label'=>'Category'],['label'=>'Status'],['label'=>'Action']]" caption="Message templates" :empty="$templates->isEmpty()" empty-title="No message templates">
        @foreach($templates as $template)<tr>
            <td><strong>{{ $template->name }}</strong><span class="d-block small text-secondary">{{ $template->key ?: 'No stable key' }}</span></td>
            <td>{{ str($template->channel)->headline() }}</td><td>{{ str($template->category)->headline() }}</td>
            <td><x-ui.status-badge :status="$template->archived_at ? 'archived' : ($template->is_active ? 'active' : 'inactive')"/></td>
            <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('message-templates.edit', $template) }}">Edit</a>
                <form class="d-inline" method="POST" action="{{ route('message-templates.archive', $template) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $template->archived_at ? 'Reactivate' : 'Archive' }}</button></form>
            </td>
        </tr>@endforeach
    </x-ui.data-table><x-ui.pagination :paginator="$templates" class="mt-4"/>
</x-layouts.app>
