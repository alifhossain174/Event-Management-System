<x-layouts.app title="Log in">
    <div class="container py-5">
        <div class="card auth-card mx-auto">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Log in</h1>
                <p class="text-secondary">Use an administrator-created account. Public registration is disabled.</p>
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Log in</button>
                </form>
                <a class="d-block mt-3" href="{{ route('password.request') }}">Forgot your password?</a>
            </div>
        </div>
    </div>
</x-layouts.app>
