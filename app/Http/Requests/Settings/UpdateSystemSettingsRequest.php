<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.configure');
    }

    public function rules(): array
    {
        return [
            'timezone' => ['required', Rule::in(config('system-settings.timezones'))],
            'currency' => ['required', Rule::in(config('system-settings.currencies'))],
            'locale' => ['required', Rule::in(array_keys(config('system-settings.locales')))],
            'date_format' => ['required', Rule::in(config('system-settings.date_formats'))],
            'default_tax_rate' => ['required', 'decimal:0,6', 'gte:0', 'lte:100'],
            'invoice_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-_\/]+$/'],
            'invoice_next_number' => ['required', 'integer', 'min:1'],
            'invoice_number_padding' => ['required', 'integer', 'between:1,12'],
            'branches_enabled' => ['required', 'boolean'],
            'client_portal_enabled' => ['required', 'boolean'],
            'vendor_portal_enabled' => ['required', 'boolean'],
            'staff_portal_enabled' => ['required', 'boolean'],
            'communications_enabled' => ['required', 'boolean'],
            'email_secret_placeholder' => ['nullable', 'string', 'max:2048'],
            'sms_secret_placeholder' => ['nullable', 'string', 'max:2048'],
            'whatsapp_secret_placeholder' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function settings(): array
    {
        $data = $this->validated();

        return [
            'general.timezone' => $data['timezone'],
            'general.currency' => $data['currency'],
            'general.locale' => $data['locale'],
            'general.date_format' => $data['date_format'],
            'finance.default_tax_rate' => $data['default_tax_rate'],
            'invoice.prefix' => $data['invoice_prefix'],
            'invoice.next_number' => $data['invoice_next_number'],
            'invoice.number_padding' => $data['invoice_number_padding'],
            'features.branches_enabled' => $data['branches_enabled'],
            'features.client_portal_enabled' => $data['client_portal_enabled'],
            'features.vendor_portal_enabled' => $data['vendor_portal_enabled'],
            'features.staff_portal_enabled' => $data['staff_portal_enabled'],
            'features.communications_enabled' => $data['communications_enabled'],
            'integrations.email_secret_placeholder' => $data['email_secret_placeholder'] ?? null,
            'integrations.sms_secret_placeholder' => $data['sms_secret_placeholder'] ?? null,
            'integrations.whatsapp_secret_placeholder' => $data['whatsapp_secret_placeholder'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['branches_enabled', 'client_portal_enabled', 'vendor_portal_enabled', 'staff_portal_enabled', 'communications_enabled'] as $key) {
            $this->merge([$key => $this->boolean($key)]);
        }

        $this->merge(['currency' => mb_strtoupper((string) $this->string('currency'))]);
    }
}
