@props(['name', 'label', 'value' => null, 'help' => null, 'required' => false])

@php($inputId = $attributes->get('id', $name))
<div class="mb-3">
    <label class="form-label" for="{{ $inputId }}">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span>@endif</label>
    <textarea {{ $attributes->except('id')->merge([
        'class' => 'form-control'.($errors->has($name) ? ' is-invalid' : ''),
        'id' => $inputId,
        'name' => $name,
        'required' => $required,
        'aria-invalid' => $errors->has($name) ? 'true' : null,
    ]) }}>{{ old($name, $value) }}</textarea>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
