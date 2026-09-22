<x-layouts.app title="Create vendor" :breadcrumbs="[['label'=>'Vendors','url'=>route('vendors.index')],['label'=>'Create']]">
<x-ui.page-header title="Create vendor" subtitle="A vendor record does not create a login account."/><form method="POST" action="{{ route('vendors.store') }}">@csrf @include('vendors._form')</form></x-layouts.app>
