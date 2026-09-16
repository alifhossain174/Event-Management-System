<x-layouts.app title="Profile">
    <main class="container py-4">
        <h1 class="h2 mb-4">Profile</h1>
        <div class="row g-4">
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="card-body">
                        <h2 class="h4">Account details</h2>
                        <form method="POST" action="{{ route('profile.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label" for="name">Name</label>
                                <input class="form-control" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required>
                            </div>
                            <button class="btn btn-primary" type="submit">Save profile</button>
                        </form>
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="card-body">
                        <h2 class="h4">Change password</h2>
                        <form method="POST" action="{{ route('profile.password.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label" for="current_password">Current password</label>
                                <input class="form-control" id="current_password" name="current_password" type="password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="new_password">New password</label>
                                <input class="form-control" id="new_password" name="password" type="password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="new_password_confirmation">Confirm new password</label>
                                <input class="form-control" id="new_password_confirmation" name="password_confirmation" type="password" required>
                            </div>
                            <button class="btn btn-primary" type="submit">Update password</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>
</x-layouts.app>
