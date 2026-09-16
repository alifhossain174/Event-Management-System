@php($editing = isset($branch))
<div class="row g-3">
    <div class="col-md-4"><x-ui.form.input name="code" label="Branch code" :value="$branch->code ?? null" maxlength="50" required/></div>
    <div class="col-md-8"><x-ui.form.input name="name" label="Branch name" :value="$branch->name ?? null" required/></div>
    <div class="col-md-6"><x-ui.form.input name="email" label="Contact email" type="email" :value="$branch->email ?? null"/></div>
    <div class="col-md-6"><x-ui.form.input name="phone" label="Contact phone" :value="$branch->phone ?? null"/></div>
</div>
<x-ui.form.input name="address_line_1" label="Address line 1" :value="$branch->address_line_1 ?? null"/>
<x-ui.form.input name="address_line_2" label="Address line 2" :value="$branch->address_line_2 ?? null"/>
<div class="row g-3">
    <div class="col-md-6"><x-ui.form.input name="city" label="City" :value="$branch->city ?? null"/></div>
    <div class="col-md-6"><x-ui.form.input name="state" label="State or region" :value="$branch->state ?? null"/></div>
    <div class="col-md-6"><x-ui.form.input name="postal_code" label="Postal code" :value="$branch->postal_code ?? null"/></div>
    <div class="col-md-6"><x-ui.form.input name="country_code" label="Country code" :value="$branch->country_code ?? null" maxlength="2"/></div>
    <div class="col-md-6"><x-ui.form.select name="timezone" label="Time zone override" :options="array_combine(config('system-settings.timezones'), config('system-settings.timezones'))" :value="$branch->timezone ?? null" placeholder="Use organization default"/></div>
</div>
<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $branch->is_active ?? true))>
    <label class="form-check-label" for="is_active">Active</label>
</div>
