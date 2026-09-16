@props(['status'])

@php
    $normalizedStatus = strtolower((string) $status);
    $variant = match ($normalizedStatus) {
        'active', 'completed', 'paid', 'approved', 'success' => 'success',
        'inactive', 'pending', 'planning', 'warning' => 'warning',
        'cancelled', 'failed', 'overdue', 'danger' => 'danger',
        'archived', 'draft', 'secondary' => 'secondary',
        default => 'info',
    };
@endphp

<span {{ $attributes->merge(['class' => "status-badge badge text-bg-{$variant}"]) }}>{{ $slot->isEmpty() ? ucfirst($normalizedStatus) : $slot }}</span>
