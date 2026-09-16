<x-layouts.app title="Audit entry" :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Audit history', 'url' => route('audit.index')], ['label' => '#'.$log->id]]">
    <x-ui.page-header :title="'Audit entry #'.$log->id" :subtitle="$log->action"/>
    <div class="row g-4">
        <div class="col-lg-5">
            <section class="card"><div class="card-body p-4"><h2 class="h4">Request context</h2><dl class="row mb-0">
                <dt class="col-sm-4">Actor</dt><dd class="col-sm-8">{{ $log->actor?->name ?? 'System' }}</dd>
                <dt class="col-sm-4">Entity</dt><dd class="col-sm-8">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</dd>
                <dt class="col-sm-4">When</dt><dd class="col-sm-8">{{ $log->occurred_at->format('Y-m-d H:i:s T') }}</dd>
                <dt class="col-sm-4">IP address</dt><dd class="col-sm-8">{{ $log->ip_address ?: '—' }}</dd>
                <dt class="col-sm-4">User agent</dt><dd class="col-sm-8 text-break mb-0">{{ $log->user_agent ?: '—' }}</dd>
            </dl></div></section>
        </div>
        <div class="col-lg-7">
            <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Before</h2><pre class="bg-body-tertiary border rounded p-3 mb-0 text-wrap">{{ json_encode($log->before_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></section>
            <section class="card"><div class="card-body p-4"><h2 class="h4">After</h2><pre class="bg-body-tertiary border rounded p-3 mb-0 text-wrap">{{ json_encode($log->after_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></section>
        </div>
    </div>
</x-layouts.app>
