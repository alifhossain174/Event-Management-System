@props(['status'])

@php
    $normalizedStatus = strtolower((string) $status);
    $variant = match ($normalizedStatus) {
        'active', 'completed', 'paid', 'approved', 'success', 'enabled', 'confirmed', 'converted' => 'success',
        'inactive', 'pending', 'planning', 'warning', 'under_review', 'waitlisted' => 'warning',
        'cancelled', 'failed', 'overdue', 'danger' => 'danger',
        'archived', 'draft', 'secondary', 'disabled' => 'secondary',
        default => 'info',
    };
@endphp

<span {{ $attributes->merge(['class' => "status-badge badge text-bg-{$variant}"]) }}>{{ $slot->isEmpty() ? ucfirst($normalizedStatus) : $slot }}</span>
