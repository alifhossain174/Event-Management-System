@php
    $selectedRoles = collect(old('role_ids', isset($managedUser) ? $managedUser->roles->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

<x-ui.form.input name="name" label="Name" :value="$managedUser->name ?? null" required autocomplete="name"/>
<x-ui.form.input name="email" label="Email" type="email" :value="$managedUser->email ?? null" required autocomplete="email"/>
<x-ui.form.input
    name="password"
    :label="isset($managedUser) ? 'New password' : 'Initial password'"
    type="password"
    :required="! isset($managedUser)"
    :help="isset($managedUser) ? 'Leave blank to keep the current password.' : 'The user can change this after signing in.'"
    autocomplete="new-password"
/>
<x-ui.form.input
    name="password_confirmation"
    label="Confirm password"
    type="password"
    :required="! isset($managedUser)"
    autocomplete="new-password"
/>
<fieldset class="mb-3">
    <legend class="form-label">Roles <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span></legend>
    <div class="row g-2">
        @foreach ($roles as $role)
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" id="role-{{ $role->id }}" name="role_ids[]" type="checkbox" value="{{ $role->id }}" @checked($selectedRoles->contains($role->id))>
                    <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                </div>
            </div>
        @endforeach
    </div>
</fieldset>
@unless (isset($managedUser))
    <div class="form-check mb-3">
        <input name="is_active" type="hidden" value="0">
        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', true))>
        <label class="form-check-label" for="is_active">Active account</label>
    </div>
@endunless

<div class="d-flex flex-wrap gap-2">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Cancel</a>
</div>
