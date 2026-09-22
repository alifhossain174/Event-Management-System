<x-layouts.app title="Notifications" wide :breadcrumbs="[['label'=>'Overview','url'=>route('home')],['label'=>'Notifications']]">
    <x-ui.page-header title="Notifications" subtitle="Your private, stored alerts. Opening a linked record still requires server-side authorization.">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="btn btn-outline-primary" type="submit">Mark all read</button></form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('notifications.index')" :clear-url="route('notifications.index')">
        <div class="col-12 col-md-4"><x-ui.form.select name="state" label="Read state" :options="['unread'=>'Unread','read'=>'Read']" :value="$filters['state'] ?? ''" placeholder="All notifications"/></div>
    </x-ui.filter-bar>

    <x-ui.data-table :columns="[['label'=>'Notification'],['label'=>'Created'],['label'=>'State'],['label'=>'Action']]" caption="Notification inbox" :empty="$recipients->isEmpty()" empty-title="No notifications">
        @foreach($recipients as $recipient)
            <tr class="{{ $recipient->read_at ? '' : 'table-primary' }}">
                <td><strong>{{ $recipient->notification->title }}</strong><span class="d-block small text-secondary">{{ $recipient->notification->body }}</span><span class="d-block small text-secondary">{{ str($recipient->notification->type)->headline() }}</span></td>
                <td>{{ $recipient->notification->created_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</td>
                <td><x-ui.status-badge :status="$recipient->read_at ? 'read' : 'pending'">{{ $recipient->read_at ? 'Read' : 'Unread' }}</x-ui.status-badge></td>
                <td class="text-nowrap">
                    @if($recipient->read_at)
                        <form class="d-inline" method="POST" action="{{ route('notifications.unread', $recipient) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">Mark unread</button></form>
                    @else
                        <form class="d-inline" method="POST" action="{{ route('notifications.read', $recipient) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-primary" type="submit">{{ $links->url($recipient->notification) ? 'Open' : 'Mark read' }}</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>
    <x-ui.pagination :paginator="$recipients" class="mt-4"/>
</x-layouts.app>
