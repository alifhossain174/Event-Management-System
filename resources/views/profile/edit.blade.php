<x-layouts.app
    title="Profile"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Profile'],
    ]"
>
    <x-ui.page-header title="Profile" subtitle="Manage your account details and password."/>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-body p-4">
                    <h2 class="h4">Account details</h2>
                    <p class="text-secondary">This name and email identify your login account.</p>
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')
                        <x-ui.form.input name="name" label="Name" :value="auth()->user()->name" required autocomplete="name"/>
                        <x-ui.form.input name="email" label="Email" type="email" :value="auth()->user()->email" required autocomplete="email"/>
                        <button class="btn btn-primary" type="submit">Save profile</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-body p-4">
                    <h2 class="h4">Change password</h2>
                    <p class="text-secondary">Use a unique password that is not shared with other services.</p>
                    <form method="POST" action="{{ route('profile.password.update') }}">
                        @csrf
                        @method('PUT')
                        <x-ui.form.input name="current_password" label="Current password" type="password" required autocomplete="current-password"/>
                        <x-ui.form.input id="new_password" name="password" label="New password" type="password" required autocomplete="new-password"/>
                        <x-ui.form.input id="new_password_confirmation" name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password"/>
                        <button class="btn btn-primary" type="submit">Update password</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
