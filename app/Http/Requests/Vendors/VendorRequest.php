<?php

namespace App\Http\Requests\Vendors;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor ? $this->user()->can('update', $vendor) : $this->user()->can('create', Vendor::class);
    }

    public function rules(): array
    {
        return ['display_name' => ['required', 'string', 'max:180'], 'legal_name' => ['nullable', 'string', 'max:180'], 'primary_email' => ['nullable', 'required_without:primary_phone', 'email:rfc', 'max:255'], 'primary_phone' => ['nullable', 'required_without:primary_email', 'string', 'max:60', 'regex:/^[0-9+()\-.\s]+$/'], 'website' => ['nullable', 'url:http,https', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'], 'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'], 'notes' => ['nullable', 'string', 'max:10000'], 'availability_conflict_policy' => ['required', Rule::in(['warn', 'block'])], 'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')], 'category_ids' => ['nullable', 'array'], 'category_ids.*' => [Rule::exists('vendor_categories', 'id')->whereNull('deleted_at')->where('is_active', true)]];
    }

    protected function prepareForValidation(): void
    {
        $values = collect($this->all())->map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value)->all();
        $values['category_ids'] = array_values(array_filter($values['category_ids'] ?? []));
        $values['availability_conflict_policy'] ??= $this->route('vendor')?->availability_conflict_policy ?? 'warn';
        $this->replace($values);
    }
}
