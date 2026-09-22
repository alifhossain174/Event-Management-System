@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])

@php
    $inputId = $attributes->get('id', $name);
    $errorId = $inputId.'-error';
    $helpId = $inputId.'-help';
    $describedBy = collect([$help ? $helpId : null, $errors->has($name) ? $errorId : null])->filter()->join(' ');
@endphp

<div class="mb-3">
    <label class="form-label" for="{{ $inputId }}">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span>@endif</label>
    <input
        {{ $attributes->except('id')->merge([
            'class' => 'form-control'.($errors->has($name) ? ' is-invalid' : ''),
            'id' => $inputId,
            'name' => $name,
            'type' => $type,
            'value' => $type === 'password' ? null : old($name, $value),
            'required' => $required,
            'aria-invalid' => $errors->has($name) ? 'true' : null,
            'aria-describedby' => $describedBy ?: null,
        ]) }}
    >
    @if ($help)<div class="form-text" id="{{ $helpId }}">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback" id="{{ $errorId }}">{{ $message }}</div>@enderror
</div>
