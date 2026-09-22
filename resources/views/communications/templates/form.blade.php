<x-layouts.app :title="$editing ? 'Edit message template' : 'Create message template'" :breadcrumbs="[['label'=>'Overview','url'=>route('home')],['label'=>'Message templates','url'=>route('message-templates.index')],['label'=>$editing ? 'Edit' : 'Create']]">
    <x-ui.page-header :title="$editing ? 'Edit message template' : 'Create message template'" subtitle="Template content is copied into each message, preserving delivery history when the template later changes."/>
    <section class="card"><div class="card-body p-4">
        <form method="POST" action="{{ $editing ? route('message-templates.update', $template) : route('message-templates.store') }}">
            @csrf @if($editing) @method('PUT') @endif
            <div class="row"><div class="col-md-8"><x-ui.form.input name="name" label="Name" :value="$template->name" required/></div><div class="col-md-4"><x-ui.form.input name="key" label="Stable key" :value="$template->key" help="Optional letters, numbers, dashes, and underscores."/></div></div>
            <div class="row"><div class="col-md-6"><x-ui.form.select name="channel" label="Channel" :options="['email'=>'Email','sms'=>'SMS (disabled adapter)','whatsapp'=>'WhatsApp (disabled adapter)']" :value="$template->channel ?: 'email'" required/></div><div class="col-md-6"><x-ui.form.select name="category" label="Category" :options="['operational'=>'Operational','marketing'=>'Marketing (consent required)']" :value="$template->category ?: 'operational'" required/></div></div>
            <x-ui.form.input name="subject" label="Subject" :value="$template->subject"/>
            <x-ui.form.textarea name="body" label="Body" :value="$template->body" rows="8" required/>
            <div class="form-check mb-4"><input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $template->exists ? $template->is_active : true))><label class="form-check-label" for="is_active">Active</label></div>
            <button class="btn btn-primary" type="submit">{{ $editing ? 'Save template' : 'Create template' }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('message-templates.index') }}">Cancel</a>
        </form>
    </div></section>
</x-layouts.app>
