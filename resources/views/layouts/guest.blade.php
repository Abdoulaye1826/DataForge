<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="dns-prefetch" href="//fonts.bunny.net">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 100vh;">
        <a href="{{ url('/') }}" class="d-flex align-items-center gap-2 mb-4 text-decoration-none">
            <span class="df-logo-mark">DF</span>
            <span class="fw-bold text-dark">{{ config('app.name') }}</span>
        </a>

        <div class="df-card" style="width: 100%; max-width: 420px;">
            @yield('content')
        </div>
    </div>
</body>
</html>
