@php
    $messages = [
        'status' => ['variant' => 'success', 'icon' => 'check'],
        'warning' => ['variant' => 'warning', 'icon' => 'alert'],
        'info' => ['variant' => 'info', 'icon' => 'info'],
        'error' => ['variant' => 'danger', 'icon' => 'alert'],
    ];
@endphp

@foreach ($messages as $key => $presentation)
    @if (session()->has($key))
        <div class="alert alert-{{ $presentation['variant'] }} alert-dismissible fade show d-flex align-items-start gap-2" role="{{ $key === 'error' ? 'alert' : 'status' }}">
            <x-ui.icon :name="$presentation['icon']" class="flex-shrink-0 mt-1"/>
            <div>{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
        </div>
    @endif
@endforeach
