<x-layouts.app :title="'Edit '.$venue->name" :breadcrumbs="[['label'=>'Venues','url'=>route('venues.index')],['label'=>$venue->name,'url'=>route('venues.show',$venue)],['label'=>'Edit']]">
    <x-ui.page-header :title="'Edit '.$venue->name"/>
    <form method="POST" action="{{ route('venues.update',$venue) }}">@csrf @method('PUT') @include('venues._form')</form>
</x-layouts.app>
