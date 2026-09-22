<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCompanyRequest;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use App\Services\CompanyService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class SettingsController extends Controller
{
    public function edit(CompanyService $companies, SettingsService $settings): View
    {
        Gate::authorize('viewAny', SystemSetting::class);
        $company = $companies->current();
        Gate::authorize('view', $company);

        return view('settings.edit', [
            'company' => $company,
            'settings' => [
                'timezone' => $settings->string('general.timezone'),
                'currency' => $settings->string('general.currency'),
                'locale' => $settings->string('general.locale'),
                'date_format' => $settings->string('general.date_format'),
                'default_tax_rate' => $settings->decimal('finance.default_tax_rate'),
                'invoice_prefix' => $settings->string('invoice.prefix'),
                'invoice_next_number' => $settings->integer('invoice.next_number'),
                'invoice_number_padding' => $settings->integer('invoice.number_padding'),
                'branches_enabled' => $settings->boolean('features.branches_enabled'),
                'client_portal_enabled' => $settings->boolean('features.client_portal_enabled'),
                'vendor_portal_enabled' => $settings->boolean('features.vendor_portal_enabled'),
                'staff_portal_enabled' => $settings->boolean('features.staff_portal_enabled'),
                'communications_enabled' => $settings->boolean('features.communications_enabled'),
            ],
            'secretConfigured' => [
                'email' => $settings->hasSecret('integrations.email_secret_placeholder'),
                'sms' => $settings->hasSecret('integrations.sms_secret_placeholder'),
                'whatsapp' => $settings->hasSecret('integrations.whatsapp_secret_placeholder'),
            ],
        ]);
    }

    public function updateCompany(UpdateCompanyRequest $request, CompanyService $companies): RedirectResponse
    {
        $company = $companies->current();
        Gate::authorize('update', $company);
        $companies->update($company, $request->validated(), $request->user());

        return back()->with('status', 'Company profile updated.');
    }

    public function updateSystem(UpdateSystemSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        Gate::authorize('settings.configure');
        $settings->update($request->settings(), $request->user());

        return back()->with('status', 'System settings updated.');
    }
}
