<x-layouts.app :title="$guest->exists ? 'Edit guest' : 'Add guest'" :breadcrumbs="[['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Guests','url'=>route('events.guests.index',$event)],['label'=>$guest->exists?'Edit':'Add']]">
    <x-ui.page-header :title="$guest->exists ? 'Edit guest' : 'Add guest'" subtitle="Guest records do not require a login account."/>
    <form method="POST" action="{{ $guest->exists ? route('events.guests.update', [$event,$guest]) : route('events.guests.store',$event) }}">
        @csrf @if($guest->exists) @method('PUT') @endif
        <x-ui.validation-summary/>
        @include('events.guests._form')
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">{{ $guest->exists ? 'Save changes' : 'Create guest' }}</button><a class="btn btn-outline-secondary" href="{{ route('events.guests.index',$event) }}">Cancel</a></div>
    </form>
</x-layouts.app>
