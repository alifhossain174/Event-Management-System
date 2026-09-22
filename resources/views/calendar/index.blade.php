<x-layouts.app
    title="Calendar"
    wide
    :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Calendar']]"
>
    <x-ui.page-header title="Consolidated calendar" subtitle="A live projection of authorized source schedules; no calendar records are duplicated."/>

    <x-ui.filter-bar :action="route('calendar.index')">
        <div class="col-md-3"><x-ui.form.select name="view" label="View" :options="['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly']" :selected="$view" required/></div>
        <div class="col-md-3"><x-ui.form.input type="date" name="date" label="Date" :value="$filters['date'] ?? $window['start']->format('Y-m-d')"/></div>
        <div class="col-md-3"><x-ui.form.select name="source" label="Source" :options="collect($sources)->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])->all()" :selected="$filters['source'] ?? ''" placeholder="All sources"/></div>
        @if($branches->isNotEmpty())<div class="col-md-3"><x-ui.form.select name="branch" label="Branch" :options="$branches->pluck('name','id')->all()" :selected="$filters['branch'] ?? ''" placeholder="All permitted branches"/></div>@endif
    </x-ui.filter-bar>

    <section class="card"><div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h2 class="h4 mb-0">{{ str($view)->headline() }} view</h2><span class="text-secondary">{{ $window['start']->format('Y-m-d') }} – {{ $window['end']->subSecond()->format('Y-m-d') }} · {{ $window['timezone'] }}</span></div>
        @forelse($entries->groupBy(fn($entry) => $entry->startsAt->setTimezone($window['timezone'])->format('Y-m-d')) as $day => $dayEntries)
            <section class="border rounded p-3 mb-3" aria-labelledby="day-{{ $loop->index }}"><h3 class="h5" id="day-{{ $loop->index }}">{{ \Carbon\CarbonImmutable::parse($day, $window['timezone'])->format('l, F j, Y') }}</h3>
                <div class="list-group list-group-flush">
                    @foreach($dayEntries as $entry)
                        @php($localStart = $entry->startsAt->setTimezone($window['timezone']))
                        @php($localEnd = $entry->endsAt?->setTimezone($window['timezone']))
                        <a class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between gap-2" href="{{ $entry->url }}">
                            <span><span class="badge text-bg-light border me-2">{{ str($entry->sourceType)->headline() }}</span><strong>{{ $entry->title }}</strong><span class="d-block small text-secondary mt-1">{{ $entry->context }}</span></span>
                            <span class="text-nowrap">{{ $localStart->format('H:i') }}@if($localEnd) – {{ $localEnd->format('H:i') }}@endif</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <x-ui.empty-state title="Nothing scheduled" description="No authorized enabled source has entries in this period."/>
        @endforelse
    </div></section>
</x-layouts.app>
