<x-layouts.app :title="'Registration forms · '.$event->name" :breadcrumbs="[['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Registration forms']]">
    <x-ui.page-header title="Registration forms" subtitle="Published forms accept registrations independently of Ticketing.">
        <x-slot:actions><a class="btn btn-outline-primary" href="{{ route('events.registrations.index',$event) }}">Submissions</a>@can('create',[App\Models\RegistrationForm::class,$event])<a class="btn btn-primary" href="{{ route('events.registration-forms.create',$event) }}">Create form</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    <x-ui.data-table :columns="[['label'=>'Form'],['label'=>'State'],['label'=>'Submissions'],['label'=>'Public URL'],['label'=>'Actions']]" caption="Event registration forms" :empty="$forms->isEmpty()" empty-title="No registration forms">
        @foreach($forms as $form)<tr>
            <td><span class="fw-semibold">{{ $form->name }}</span><span class="d-block small text-secondary">{{ str($form->duplicate_policy)->headline() }}</span></td>
            <td><x-ui.status-badge :status="$form->published_at ? 'published' : ($form->is_active ? 'draft' : 'inactive')"/></td>
            <td>{{ $form->registrations_count }}</td>
            <td>@if($form->isPublished())<a href="{{ route('public.registrations.show',$form->public_slug) }}" target="_blank" rel="noopener">Open public form</a>@else — @endif</td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('events.registration-forms.edit',[$event,$form]) }}">Builder</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('events.registrations.create',[$event,$form]) }}">Offline entry</a></td>
        </tr>@endforeach
    </x-ui.data-table>
    <div class="mt-3">{{ $forms->links() }}</div>
</x-layouts.app>
