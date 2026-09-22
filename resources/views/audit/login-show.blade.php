<x-layouts.app title="Login attempt" :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Audit history', 'url' => route('audit.index')], ['label' => 'Login history', 'url' => route('audit.logins.index')], ['label' => '#'.$history->id]]">
    <x-ui.page-header :title="'Login attempt #'.$history->id" subtitle="Authentication history is append-only."/>
    <section class="card"><div class="card-body p-4"><dl class="row mb-0">
        <dt class="col-sm-3">Account identifier</dt><dd class="col-sm-9">{{ $history->email }}</dd>
        <dt class="col-sm-3">Known user</dt><dd class="col-sm-9">{{ $history->user?->name ?? 'No matching retained account' }}</dd>
        <dt class="col-sm-3">Result</dt><dd class="col-sm-9">{{ $history->successful ? 'Success' : 'Failure' }}</dd>
        <dt class="col-sm-3">Internal reason</dt><dd class="col-sm-9">{{ $history->failure_reason ?: '—' }}</dd>
        <dt class="col-sm-3">When</dt><dd class="col-sm-9">{{ $history->attempted_at->format('Y-m-d H:i:s T') }}</dd>
        <dt class="col-sm-3">IP address</dt><dd class="col-sm-9">{{ $history->ip_address ?: '—' }}</dd>
        <dt class="col-sm-3">User agent</dt><dd class="col-sm-9 text-break mb-0">{{ $history->user_agent ?: '—' }}</dd>
    </dl></div></section>
</x-layouts.app>
