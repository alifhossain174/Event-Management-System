@php
    $selectedRoles = collect(old('role_ids', isset($managedUser) ? $managedUser->roles->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

<div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $managedUser->name ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $managedUser->email ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="password">{{ isset($managedUser) ? 'New password (optional)' : 'Initial password' }}</label>
    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" {{ isset($managedUser) ? '' : 'required' }}>
</div>
<div class="mb-3">
    <label class="form-label" for="password_confirmation">Confirm password</label>
    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" {{ isset($managedUser) ? '' : 'required' }}>
</div>
<fieldset class="mb-3">
    <legend class="form-label">Roles</legend>
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

<button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
<a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Cancel</a>
