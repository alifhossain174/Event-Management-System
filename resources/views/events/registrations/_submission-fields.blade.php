<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="registrant_name">Name</label><input class="form-control" id="registrant_name" name="registrant_name" required maxlength="191" value="{{ old('registrant_name') }}"></div>
    <div class="col-md-3"><label class="form-label" for="registrant_email">Email</label><input class="form-control" id="registrant_email" name="registrant_email" type="email" maxlength="320" value="{{ old('registrant_email') }}" @required($registrationForm->duplicate_policy==='block_email'||$registrationForm->confirmation_channel==='email')></div>
    <div class="col-md-3"><label class="form-label" for="registrant_phone">Phone</label><input class="form-control" id="registrant_phone" name="registrant_phone" maxlength="50" value="{{ old('registrant_phone') }}" @required($registrationForm->confirmation_channel==='sms')></div>
    @foreach($registrationForm->fields as $field)
        @php($name='responses['.$field->key.']')
        <div class="{{ $field->type==='textarea' ? 'col-12' : 'col-md-6' }}">
            <label class="form-label" for="field_{{ $field->id }}">{{ $field->label }}@if($field->is_required) <span aria-hidden="true">*</span>@endif</label>
            @if($field->type==='textarea')<textarea class="form-control" id="field_{{ $field->id }}" name="{{ $name }}" rows="4" @required($field->is_required)>{{ old('responses.'.$field->key) }}</textarea>
            @elseif(in_array($field->type,['select','radio'],true))<select class="form-select" id="field_{{ $field->id }}" name="{{ $name }}" @required($field->is_required)><option value="">Select</option>@foreach($field->options ?? [] as $option)<option value="{{ $option }}" @selected(old('responses.'.$field->key)===$option)>{{ $option }}</option>@endforeach</select>
            @elseif($field->type==='checkbox')<div id="field_{{ $field->id }}">@foreach($field->options ?? [] as $index=>$option)<div class="form-check"><input class="form-check-input" id="field_{{ $field->id }}_{{ $index }}" type="checkbox" name="{{ $name }}[]" value="{{ $option }}" @checked(in_array($option,old('responses.'.$field->key,[])))><label class="form-check-label" for="field_{{ $field->id }}_{{ $index }}">{{ $option }}</label></div>@endforeach</div>
            @elseif($field->type==='consent')<div class="form-check"><input type="hidden" name="{{ $name }}" value="0"><input class="form-check-input" id="field_{{ $field->id }}" type="checkbox" name="{{ $name }}" value="1" @checked(old('responses.'.$field->key)) @required($field->is_required)><label class="form-check-label" for="field_{{ $field->id }}">I agree</label></div>
            @else<input class="form-control" id="field_{{ $field->id }}" name="{{ $name }}" type="{{ $field->type==='phone'?'tel':$field->type }}" value="{{ old('responses.'.$field->key) }}" @required($field->is_required)>
            @endif
            @if($field->help_text)<div class="form-text">{{ $field->help_text }}</div>@endif
        </div>
    @endforeach
</div>
