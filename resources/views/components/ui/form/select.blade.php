@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false])

@php($inputId = $attributes->get('id', $name))
<div class="mb-3">
    <label class="form-label" for="{{ $inputId }}">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span>@endif</label>
    <select {{ $attributes->except('id')->merge([
        'class' => 'form-select'.($errors->has($name) ? ' is-invalid' : ''),
        'id' => $inputId,
        'name' => $name,
        'required' => $required,
        'aria-invalid' => $errors->has($name) ? 'true' : null,
    ]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
