@props(['code', 'title', 'message'])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $code }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="error-body">
    <main class="container d-flex min-vh-100 align-items-center justify-content-center py-5">
        <section class="error-card text-center" aria-labelledby="error-title">
            <p class="error-code mb-2">{{ $code }}</p>
            <h1 class="h2" id="error-title">{{ $title }}</h1>
            <p class="text-secondary mx-auto mb-4">{{ $message }}</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-primary" href="{{ route('home') }}">Return home</a>
                @guest
                    <a class="btn btn-outline-secondary" href="{{ route('login') }}">Log in</a>
                @endguest
            </div>
        </section>
    </main>
</body>
</html>
