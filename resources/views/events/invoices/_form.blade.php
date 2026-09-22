@php
    $rows = old('items', collect($items)->map(fn ($item) => $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : $item)->all());
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <label class="form-label" for="due_date">Due date</label>
        <input class="form-control" id="due_date" name="due_date" type="date" required value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="tax_label">Tax label</label>
        <input class="form-control" id="tax_label" name="tax_label" required maxlength="100" value="{{ old('tax_label', $invoice->tax_label ?: 'Tax') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="default_tax_rate">Default tax rate percent</label>
        <input class="form-control" id="default_tax_rate" name="default_tax_rate" type="number" min="0" max="100" step="0.000001" required value="{{ old('default_tax_rate', $invoice->default_tax_rate ?: '0.000000') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="subject">Subject</label>
        <input class="form-control" id="subject" name="subject" maxlength="191" value="{{ old('subject', $invoice->subject) }}" placeholder="Event services">
    </div>
    @if($event->booking_id)
        <div class="col-md-6">
            <label class="form-label" for="booking_id">Optional linked Booking</label>
            <select class="form-select" id="booking_id" name="booking_id">
                <option value="">No Booking reference</option>
                <option value="{{ $event->booking_id }}" @selected((string) old('booking_id', $invoice->booking_id) === (string) $event->booking_id)>{{ $event->booking?->reference_number }}</option>
            </select>
        </div>
    @endif
    <div class="col-12">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="5000">{{ old('notes', $invoice->notes) }}</textarea>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 mb-0">Line items</h2>
    <button class="btn btn-outline-primary btn-sm" id="add-invoice-item" type="button">Add line</button>
</div>
<p class="small text-secondary">Each line is rounded to four decimal places. Discount is applied before tax; issued snapshots cannot be edited.</p>
<div class="table-responsive">
    <table class="table align-middle" id="invoice-items-table">
        <thead><tr><th>Description</th><th>Quantity</th><th>Unit price</th><th>Discount</th><th>Value</th><th>Tax %</th><th><span class="visually-hidden">Remove</span></th></tr></thead>
        <tbody>
        @foreach($rows as $index => $item)
            <tr>
                <td><input class="form-control" name="items[{{ $index }}][description]" required maxlength="500" value="{{ $item['description'] ?? '' }}"></td>
                <td><input class="form-control" name="items[{{ $index }}][quantity]" type="number" min="0.0001" step="0.0001" required value="{{ $item['quantity'] ?? '1.0000' }}"></td>
                <td><input class="form-control" name="items[{{ $index }}][unit_price]" type="number" min="0" step="0.0001" required value="{{ $item['unit_price'] ?? '0.0000' }}"></td>
                <td><select class="form-select" name="items[{{ $index }}][discount_type]" required>@foreach(['none' => 'None', 'percentage' => 'Percent', 'fixed' => 'Fixed'] as $value => $label)<option value="{{ $value }}" @selected(($item['discount_type'] ?? 'none') === $value)>{{ $label }}</option>@endforeach</select></td>
                <td><input class="form-control" name="items[{{ $index }}][discount_value]" type="number" min="0" step="0.000001" required value="{{ $item['discount_value'] ?? '0.000000' }}"></td>
                <td><input class="form-control" name="items[{{ $index }}][tax_rate]" type="number" min="0" max="100" step="0.000001" required value="{{ $item['tax_rate'] ?? $invoice->default_tax_rate ?? '0.000000' }}"></td>
                <td><button class="btn btn-outline-danger btn-sm remove-invoice-item" type="button" aria-label="Remove line">×</button></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<template id="invoice-item-template">
    <tr>
        <td><input class="form-control" name="items[__INDEX__][description]" required maxlength="500"></td>
        <td><input class="form-control" name="items[__INDEX__][quantity]" type="number" min="0.0001" step="0.0001" required value="1.0000"></td>
        <td><input class="form-control" name="items[__INDEX__][unit_price]" type="number" min="0" step="0.0001" required value="0.0000"></td>
        <td><select class="form-select" name="items[__INDEX__][discount_type]" required><option value="none">None</option><option value="percentage">Percent</option><option value="fixed">Fixed</option></select></td>
        <td><input class="form-control" name="items[__INDEX__][discount_value]" type="number" min="0" step="0.000001" required value="0.000000"></td>
        <td><input class="form-control" name="items[__INDEX__][tax_rate]" type="number" min="0" max="100" step="0.000001" required value="{{ $invoice->default_tax_rate ?? '0.000000' }}"></td>
        <td><button class="btn btn-outline-danger btn-sm remove-invoice-item" type="button" aria-label="Remove line">×</button></td>
    </tr>
</template>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Save Draft</button>
    <a class="btn btn-outline-secondary" href="{{ route('events.invoices.index', $event) }}">Cancel</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.querySelector('#invoice-items-table tbody');
    const template = document.querySelector('#invoice-item-template');
    document.querySelector('#add-invoice-item')?.addEventListener('click', () => {
        const index = Date.now();
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
    });
    body?.addEventListener('click', (event) => {
        if (event.target.closest('.remove-invoice-item') && body.children.length > 1) event.target.closest('tr').remove();
    });
});
</script>
