<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    <link rel="dns-prefetch" href="//fonts.bunny.net">

    {{-- Resolve the theme synchronously, before first paint, so there is
         never a flash of the wrong theme while the bundled JS loads. --}}
    <script>
        (function () {
            var stored = localStorage.getItem('df-theme');
            var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="df-app">
        <aside class="df-sidebar">
            <a href="{{ route('dashboard') }}" class="df-sidebar-brand">
                <span class="df-logo-mark">DF</span>
                <span class="df-sidebar-brand-name">{{ config('app.name') }}</span>
            </a>

            <nav class="df-nav">
                <div class="df-nav-section-title">Espace de travail</div>

                <a href="{{ route('dashboard') }}" class="df-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="df-ic">▤</span> Dashboard
                </a>
                <a href="{{ route('projects.index') }}" class="df-nav-link {{ request()->routeIs('projects.*') && ! isset($project) ? 'active' : '' }}">
                    <span class="df-ic">▣</span> Projets
                </a>

                @isset($project)
                    <div class="df-nav-section-title">{{ \Illuminate\Support\Str::limit($project->name, 22) }}</div>

                    <a href="{{ route('projects.show', $project) }}" class="df-nav-link {{ request()->routeIs('projects.show') ? 'active' : '' }}">
                        <span class="df-ic">◆</span> Aperçu
                    </a>
                    <a href="{{ route('projects.notebook.show', $project) }}" class="df-nav-link {{ request()->routeIs('projects.notebook.*') ? 'active' : '' }}">
                        <span class="df-ic">≣</span> Notebook
                    </a>
                    <a href="{{ route('projects.dashboards.index', $project) }}" class="df-nav-link {{ request()->routeIs('projects.dashboards.*') ? 'active' : '' }}">
                        <span class="df-ic">◫</span> Dashboards
                    </a>
                    <a href="{{ route('projects.relationships.index', $project) }}" class="df-nav-link {{ request()->routeIs('projects.relationships.*') ? 'active' : '' }}">
                        <span class="df-ic">⇄</span> Relations
                    </a>
                    <a href="{{ route('projects.reports.index', $project) }}" class="df-nav-link {{ request()->routeIs('projects.reports.*') ? 'active' : '' }}">
                        <span class="df-ic">▥</span> Rapports
                    </a>
                    <a href="{{ route('projects.assistant.index', $project) }}" class="df-nav-link {{ request()->routeIs('projects.assistant.*') ? 'active' : '' }}">
                        <span class="df-ic">✦</span> Assistant IA
                    </a>
                @endisset
            </nav>

            <div class="df-sidebar-footer">
                <button type="button" class="df-theme-toggle" data-theme-toggle title="Basculer clair/sombre">
                    <span class="icon-light">☾</span>
                    <span class="icon-dark">☀</span>
                </button>
                <div class="dropdown flex-fill">
                    <a href="#" class="df-nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ Auth::user()->name }}
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Se déconnecter
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <div class="df-main">
            <header class="df-topbar">
                @hasSection('breadcrumb')
                    <div class="df-breadcrumb">@yield('breadcrumb')</div>
                @else
                    <div class="df-topbar-title">@yield('title', 'Dashboard')</div>
                @endif
            </header>

            <main class="df-content">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
