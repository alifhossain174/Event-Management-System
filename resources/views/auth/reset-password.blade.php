<x-layouts.app title="Choose a new password">
    <div class="container py-5">
        <div class="card auth-card mx-auto">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Choose a new password</h1>
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Confirm password</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Reset password</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
