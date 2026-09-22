<x-ui.validation-summary class="mb-4"/>
<div class="row g-4">
    <div class="col-lg-7"><section class="card"><div class="card-body p-4">
        <h2 class="h4">Venue details</h2>
        <x-ui.form.input name="name" label="Venue name" :value="$venue->name ?? null" required/>
        <x-ui.form.select name="type" label="Ownership type" :options="['owned'=>'Owned','third_party'=>'Third party']" :value="$venue->type ?? 'third_party'" required/>
        <div class="row"><div class="col-md-6"><x-ui.form.input name="capacity" label="Whole-venue capacity" type="number" min="1" :value="$venue->capacity ?? null"/></div><div class="col-md-6"><x-ui.form.input name="parking_capacity" label="Parking capacity" type="number" min="0" :value="$venue->parking_capacity ?? null"/></div></div>
        <x-ui.form.select name="branch_id" label="Branch" :options="$branches->pluck('name','id')" :value="$venue->branch_id ?? null" placeholder="Unscoped / default"/>
    </div></section></div>
    <div class="col-lg-5"><section class="card"><div class="card-body p-4">
        <h2 class="h4">Address and map</h2>
        <x-ui.form.input name="address_line_1" label="Address line 1" :value="$venue->address_line_1 ?? null"/>
        <x-ui.form.input name="address_line_2" label="Address line 2" :value="$venue->address_line_2 ?? null"/>
        <div class="row"><div class="col-md-6"><x-ui.form.input name="city" label="City" :value="$venue->city ?? null"/></div><div class="col-md-6"><x-ui.form.input name="state_region" label="State / region" :value="$venue->state_region ?? null"/></div></div>
        <div class="row"><div class="col-md-6"><x-ui.form.input name="postal_code" label="Postal code" :value="$venue->postal_code ?? null"/></div><div class="col-md-6"><x-ui.form.input name="country_code" label="Country code" maxlength="2" :value="$venue->country_code ?? null"/></div></div>
        <div class="row"><div class="col-md-6"><x-ui.form.input name="latitude" label="Latitude" type="number" step="0.0000001" :value="$venue->latitude ?? null"/></div><div class="col-md-6"><x-ui.form.input name="longitude" label="Longitude" type="number" step="0.0000001" :value="$venue->longitude ?? null"/></div></div>
        <x-ui.form.textarea name="notes" label="Internal notes" :value="$venue->notes ?? null" rows="4"/>
    </div></section></div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">{{ isset($venue) ? 'Save changes' : 'Create venue' }}</button><a class="btn btn-outline-secondary" href="{{ isset($venue) ? route('venues.show',$venue) : route('venues.index') }}">Cancel</a></div>
