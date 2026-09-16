<x-layouts.app title="Upload document" :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Documents', 'url' => route('documents.index')], ['label' => 'Upload']]">
    <x-ui.page-header title="Upload protected document" subtitle="The file is stored outside the public web path with a randomized name."/>
    <x-ui.validation-summary/>
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card"><div class="card-body p-4">
        @csrf
        <div class="row g-3">
            <div class="col-md-8"><x-ui.form.input name="title" label="Title" required/></div>
            <div class="col-md-4"><x-ui.form.select name="document_category_id" label="Category"><option value="">Uncategorized</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('document_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</x-ui.form.select></div>
            <div class="col-12"><x-ui.form.textarea name="description" label="Description" rows="3"/></div>
            <div class="col-md-5"><x-ui.form.input name="expiry_date" type="date" label="Expiry date" help="Optional; later alerts may use this date."/></div>
            <div class="col-12"><label class="form-label" for="file">File <span class="text-danger">*</span></label><input class="form-control @error('file') is-invalid @enderror" type="file" id="file" name="file" required aria-describedby="file-help"><div id="file-help" class="form-text">PDF, Office document, image, or MP4/MOV; maximum {{ (int)config('documents.max_kilobytes') / 1024 }} MB.</div>@error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><x-ui.form.textarea name="version_notes" label="Version notes" rows="2" help="Optional note for the initial version."/></div>
        </div>
    </div><div class="card-footer d-flex gap-2 justify-content-end"><a class="btn btn-outline-secondary" href="{{ route('documents.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Upload securely</button></div></form>
</x-layouts.app>
