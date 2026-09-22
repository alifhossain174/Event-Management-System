<div class="row g-3">
    <div class="col-md-6"><x-ui.form.input name="name" label="Contact name" :value="$contact->name ?? null" required/></div>
    <div class="col-md-6"><x-ui.form.input name="relationship_label" label="Relationship" :value="$contact->relationship_label ?? null" placeholder="Assistant, spouse, coordinator"/></div>
    <div class="col-md-6"><x-ui.form.input name="job_title" label="Job title" :value="$contact->job_title ?? null"/></div>
    <div class="col-md-6"><x-ui.form.input name="email" label="Email" type="email" :value="$contact->email ?? null"/></div>
    <div class="col-md-6"><x-ui.form.input name="phone" label="Phone" type="tel" :value="$contact->phone ?? null"/></div>
    <div class="col-md-6 d-flex align-items-center">
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="is_primary" @checked(old('is_primary', $contact->is_primary ?? false))>
            <label class="form-check-label" for="is_primary">Primary contact</label>
        </div>
    </div>
    <div class="col-12"><x-ui.form.textarea name="notes" label="Contact notes" :value="$contact->notes ?? null" rows="3"/></div>
</div>
