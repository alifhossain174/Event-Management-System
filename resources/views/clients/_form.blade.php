@php($editing = isset($client))

<x-ui.validation-summary class="mb-4"/>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="card">
            <div class="card-body p-4">
                <h2 class="h4">Identity and contact</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.form.select
                            name="type"
                            label="Client type"
                            :options="['individual' => 'Individual', 'organization' => 'Organization']"
                            :value="$client->type ?? 'individual'"
                            required
                        />
                    </div>
                    <div class="col-md-6">
                        <x-ui.form.select
                            name="branch_id"
                            label="Branch"
                            :options="$branches->pluck('name', 'id')"
                            :value="$client->branch_id ?? null"
                            placeholder="Unscoped / default branch"
                        />
                    </div>
                    <div class="col-md-6"><x-ui.form.input name="first_name" label="First name" :value="$client->first_name ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="last_name" label="Last name" :value="$client->last_name ?? null"/></div>
                    <div class="col-12"><x-ui.form.input name="organization_name" label="Organization name" :value="$client->organization_name ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="primary_email" label="Primary email" type="email" :value="$client->primary_email ?? null" help="Email or phone is required. Shared household contact details are allowed."/></div>
                    <div class="col-md-6"><x-ui.form.input name="primary_phone" label="Primary phone" type="tel" :value="$client->primary_phone ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="website" label="Website" type="url" :value="$client->website ?? null" placeholder="https://example.com"/></div>
                    <div class="col-md-6"><x-ui.form.input name="legal_name" label="Legal name" :value="$client->legal_name ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="registration_number" label="Registration number" :value="$client->registration_number ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="tax_identifier" label="Tax identifier" :value="$client->tax_identifier ?? null"/></div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-lg-5">
        <section class="card mb-4">
            <div class="card-body p-4">
                <h2 class="h4">Address</h2>
                <x-ui.form.input name="address_line_1" label="Address line 1" :value="$client->address_line_1 ?? null"/>
                <x-ui.form.input name="address_line_2" label="Address line 2" :value="$client->address_line_2 ?? null"/>
                <div class="row g-3">
                    <div class="col-md-6"><x-ui.form.input name="city" label="City" :value="$client->city ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="state_region" label="State / region" :value="$client->state_region ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="postal_code" label="Postal code" :value="$client->postal_code ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="country_code" label="Country code" :value="$client->country_code ?? null" maxlength="2" placeholder="BD"/></div>
                </div>
            </div>
        </section>
        <section class="card">
            <div class="card-body p-4">
                <h2 class="h4">Internal notes</h2>
                <x-ui.form.textarea name="notes" label="Notes" :value="$client->notes ?? null" rows="6" help="Visible only to authorized application users."/>
            </div>
        </section>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create client' }}</button>
    <a class="btn btn-outline-secondary" href="{{ $editing ? route('clients.show', $client) : route('clients.index') }}">Cancel</a>
</div>
