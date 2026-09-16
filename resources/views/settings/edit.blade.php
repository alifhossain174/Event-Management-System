<x-layouts.app
    title="Organization settings"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Organization settings'],
    ]"
>
    <x-ui.page-header title="Organization settings" subtitle="Manage company identity, formatting, finance defaults, feature flags, and encrypted provider placeholders."/>
    @include('settings._navigation')

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <form class="card h-100" method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card-header bg-white">
                    <h2 class="h5 mb-1">Company profile</h2>
                    <p class="small text-secondary mb-0">The single-company profile used on later documents and communications.</p>
                </div>
                <div class="card-body">
                    <x-ui.form.input name="name" label="Organization name" :value="$company->name" required/>
                    <div class="row g-3">
                        <div class="col-md-6"><x-ui.form.input name="email" label="Contact email" type="email" :value="$company->email"/></div>
                        <div class="col-md-6"><x-ui.form.input name="phone" label="Contact phone" :value="$company->phone"/></div>
                    </div>
                    <x-ui.form.input name="website" label="Website" type="url" :value="$company->website" placeholder="https://example.com"/>
                    <x-ui.form.input name="address_line_1" label="Address line 1" :value="$company->address_line_1"/>
                    <x-ui.form.input name="address_line_2" label="Address line 2" :value="$company->address_line_2"/>
                    <div class="row g-3">
                        <div class="col-md-6"><x-ui.form.input name="city" label="City" :value="$company->city"/></div>
                        <div class="col-md-6"><x-ui.form.input name="state" label="State or region" :value="$company->state"/></div>
                        <div class="col-md-6"><x-ui.form.input name="postal_code" label="Postal code" :value="$company->postal_code"/></div>
                        <div class="col-md-6"><x-ui.form.input name="country_code" label="Country code" :value="$company->country_code" maxlength="2" help="Two-letter ISO country code."/></div>
                    </div>
                    <x-ui.form.input name="logo" label="Organization logo" type="file" accept="image/jpeg,image/png,image/webp" help="JPG, PNG, or WebP up to 2 MB. Branding media is stored on the public disk."/>
                    @if ($company->logo_original_name)
                        <p class="small text-secondary mb-0">Current logo: {{ $company->logo_original_name }} · {{ $company->logo_mime_type }} · {{ number_format(($company->logo_size ?? 0) / 1024, 1) }} KB</p>
                    @endif
                </div>
                <div class="card-footer bg-white text-end">
                    @can('update', $company)<button class="btn btn-primary" type="submit">Save company profile</button>@endcan
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-6">
            <form class="card" method="POST" action="{{ route('settings.system.update') }}">
                @csrf
                @method('PUT')
                <div class="card-header bg-white">
                    <h2 class="h5 mb-1">System defaults</h2>
                    <p class="small text-secondary mb-0">Typed values are cached through Laravel's configured file or database-compatible cache store.</p>
                </div>
                <div class="card-body">
                    <h3 class="h6 text-uppercase text-secondary">Regional formatting</h3>
                    <div class="row g-3">
                        <div class="col-md-6"><x-ui.form.select name="timezone" label="Time zone" :options="array_combine(config('system-settings.timezones'), config('system-settings.timezones'))" :value="$settings['timezone']" required/></div>
                        <div class="col-md-6"><x-ui.form.select name="currency" label="Currency" :options="array_combine(config('system-settings.currencies'), config('system-settings.currencies'))" :value="$settings['currency']" required/></div>
                        <div class="col-md-6"><x-ui.form.select name="locale" label="Language" :options="config('system-settings.locales')" :value="$settings['locale']" required/></div>
                        <div class="col-md-6"><x-ui.form.select name="date_format" label="Date format" :options="array_combine(config('system-settings.date_formats'), config('system-settings.date_formats'))" :value="$settings['date_format']" required/></div>
                    </div>

                    <hr>
                    <h3 class="h6 text-uppercase text-secondary">Finance and invoicing</h3>
                    <div class="row g-3">
                        <div class="col-md-6"><x-ui.form.input name="default_tax_rate" label="Default tax rate percent" :value="$settings['default_tax_rate']" inputmode="decimal" required/></div>
                        <div class="col-md-6"><x-ui.form.input name="invoice_prefix" label="Invoice prefix" :value="$settings['invoice_prefix']" required/></div>
                        <div class="col-md-6"><x-ui.form.input name="invoice_next_number" label="Next invoice sequence" type="number" min="1" :value="$settings['invoice_next_number']" required/></div>
                        <div class="col-md-6"><x-ui.form.input name="invoice_number_padding" label="Sequence padding" type="number" min="1" max="12" :value="$settings['invoice_number_padding']" required/></div>
                    </div>

                    <hr>
                    <h3 class="h6 text-uppercase text-secondary">Feature flags</h3>
                    <p class="small text-secondary">Flags are reversible configuration. Branch mode and every portal remain disabled by default.</p>
                    @foreach ([
                        'branches_enabled' => 'Enable branch-scoped operation',
                        'client_portal_enabled' => 'Enable client portal capability',
                        'vendor_portal_enabled' => 'Enable vendor portal capability',
                        'staff_portal_enabled' => 'Enable staff portal capability',
                        'communications_enabled' => 'Enable communication capability',
                    ] as $key => $label)
                        <input type="hidden" name="{{ $key }}" value="0">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $key }}" name="{{ $key }}" value="1" @checked(old($key, $settings[$key]))>
                            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach

                    <hr>
                    <h3 class="h6 text-uppercase text-secondary">Provider placeholders</h3>
                    <p class="small text-secondary">These encrypted placeholders do not configure or activate a provider. Leave blank to keep any saved value.</p>
                    @foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $key => $label)
                        <div class="d-flex align-items-start gap-3">
                            <div class="flex-grow-1">
                                <x-ui.form.input :name="$key.'_secret_placeholder'" :label="$label.' secret placeholder'" type="password" autocomplete="new-password" help="Saved values are encrypted and never returned to the browser."/>
                            </div>
                            <x-ui.status-badge :status="$secretConfigured[$key] ? 'active' : 'inactive'" class="mt-4">{{ $secretConfigured[$key] ? 'Saved' : 'Not set' }}</x-ui.status-badge>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer bg-white text-end">
                    @can('settings.configure')<button class="btn btn-primary" type="submit">Save system settings</button>@endcan
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
