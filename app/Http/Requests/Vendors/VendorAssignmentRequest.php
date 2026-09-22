<?php

namespace App\Http\Requests\Vendors;

use App\Models\VendorAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VendorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment
            ? $this->user()->hasPermission('vendors.assign') && $this->user()->can('view', $assignment)
            : $this->user()->can('create', VendorAssignment::class);
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'vendor_category_id' => ['required', 'integer', 'exists:vendor_categories,id'],
            'scope' => ['required', 'string', 'max:10000'],
            'scheduled_starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'scheduled_ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:scheduled_starts_at_local'],
            'quoted_cost' => ['nullable', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
            'approved_cost' => ['nullable', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'responsible_manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'delivery_status' => ['required', Rule::in(['pending', 'scheduled', 'in_progress', 'delivered', 'issue'])],
            'delivery_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, mixed> */
    public function assignmentAttributes(): array
    {
        $data = $this->validated();
        $timezone = $this->route('event')->timezone;
        $data['scheduled_starts_at'] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['scheduled_starts_at_local'], $timezone)->utc();
        $data['scheduled_ends_at'] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['scheduled_ends_at_local'], $timezone)->utc();
        unset($data['scheduled_starts_at_local'], $data['scheduled_ends_at_local']);
        $data['currency_code'] = mb_strtoupper($data['currency_code']);

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->replace(collect($this->all())->map(fn ($value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value))->all());
    }
}
