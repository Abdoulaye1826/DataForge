<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Connexion') · {{ config('app.name') }}</title>

    <link rel="dns-prefetch" href="//fonts.bunny.net">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="df-auth-shell">
        <div class="df-auth-form-panel">
            <a href="{{ url('/') }}" class="df-auth-brand">
                <span class="df-logo-mark">DF</span>
                <span class="df-auth-brand-name">{{ config('app.name') }}</span>
            </a>

            <div class="df-auth-form-wrap">
                @yield('content')
            </div>

            <p class="df-auth-footer">&copy; {{ now()->year }} {{ config('app.name') }} — l'analyse de données assistée par l'IA.</p>
        </div>

        <div class="df-auth-hero-panel">
            <div class="df-auth-hero-content">
                <span class="df-auth-hero-kicker">Espace data analyst</span>
                <h2 class="df-auth-hero-title">Forgez la donnée en décision.</h2>
                <p class="df-auth-hero-sub">
                    Importez vos fichiers, laissez l'IA nettoyer, analyser et construire vos premiers
                    tableaux de bord — sans écrire une ligne de code.
                </p>

                <ul class="df-auth-hero-features">
                    <li class="df-auth-hero-feature">
                        <span class="df-ic">◆</span>
                        <span><strong>Import instantané</strong><br>CSV, Excel, JSON, Parquet ou une base SQL en direct.</span>
                    </li>
                    <li class="df-auth-hero-feature">
                        <span class="df-ic">✦</span>
                        <span><strong>Suggestions IA</strong><br>Nettoyage, visualisations et pipeline proposés et justifiés.</span>
                    </li>
                    <li class="df-auth-hero-feature">
                        <span class="df-ic">▥</span>
                        <span><strong>Dashboards exportables</strong><br>Construits en glisser-déposer, partagés en PDF.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
