<x-layouts.app title="Forgot password">
    <div class="container py-5">
        <div class="card auth-card mx-auto">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Reset password</h1>
                <p class="text-secondary">Enter your active account email. If it matches an account, a reset link will be sent.</p>
                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                    </div>
                    <button class="btn btn-primary" type="submit">Send reset link</button>
                    <a class="btn btn-link" href="{{ route('login') }}">Back to login</a>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
