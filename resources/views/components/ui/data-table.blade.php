@props([
    'columns',
    'caption' => null,
    'empty' => false,
    'emptyTitle' => 'No records found',
    'emptyDescription' => null,
])

<div {{ $attributes->merge(['class' => 'card data-table-card']) }}>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            @if ($caption)
                <caption class="visually-hidden">{{ $caption }}</caption>
            @endif
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col" @class([$column['class'] ?? null])>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if ($empty)
                    <tr>
                        <td colspan="{{ count($columns) }}">
                            <x-ui.empty-state :title="$emptyTitle" :description="$emptyDescription" class="border-0 py-5"/>
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>
</div>
