@props(['paginator'])

@if ($paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'pagination-wrap mt-3']) }}>
        {{ $paginator->onEachSide(1)->links() }}
    </div>
@endif
