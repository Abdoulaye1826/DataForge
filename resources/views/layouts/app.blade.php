<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Workspace') · {{ config('app.name') }}</title>

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
            <a href="{{ route('workspace') }}" class="df-sidebar-brand">
                <span class="df-logo-mark">DF</span>
                <span class="df-sidebar-brand-name">{{ config('app.name') }}</span>
            </a>

            <nav class="df-nav">
                <div class="df-nav-section-title">Espace de travail</div>

                <a href="{{ route('workspace') }}" class="df-nav-link {{ request()->routeIs('workspace') ? 'active' : '' }}">
                    <span class="df-ic">▲</span> Workspace
                </a>
                <a href="{{ route('projects.index') }}" class="df-nav-link {{ request()->routeIs('projects.*') && ! isset($project) ? 'active' : '' }}">
                    <span class="df-ic">▣</span> Projets
                </a>
                <a href="{{ route('datasets.index') }}" class="df-nav-link {{ request()->routeIs('datasets.index') ? 'active' : '' }}">
                    <span class="df-ic">◆</span> Datasets
                </a>
                <a href="{{ route('pipelines.index') }}" class="df-nav-link {{ request()->routeIs('pipelines.index') ? 'active' : '' }}">
                    <span class="df-ic">⇢</span> Pipelines
                </a>
                <a href="{{ route('reports.index') }}" class="df-nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                    <span class="df-ic">▥</span> Rapports
                </a>
                <a href="{{ route('history.index') }}" class="df-nav-link {{ request()->routeIs('history.index') ? 'active' : '' }}">
                    <span class="df-ic">◷</span> Historique
                </a>

                <div class="df-nav-section-title">Système</div>

                <a href="{{ route('analytics') }}" class="df-nav-link {{ request()->routeIs('analytics') ? 'active' : '' }}">
                    <span class="df-ic">▤</span> Analytics
                </a>
                <a href="{{ route('settings.index') }}" class="df-nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <span class="df-ic">⚙</span> Paramètres
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
                    <div class="df-topbar-title">@yield('title', 'Workspace')</div>
                @endif

                <button type="button" class="df-command-trigger" data-command-trigger>
                    <span>Rechercher ou naviguer…</span>
                    <kbd>Ctrl K</kbd>
                </button>
            </header>

            <main class="df-content">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    {{-- Palette de commandes (Ctrl/Cmd+K) : navigation rapide vers un projet
         ou une action globale, sans dépendre d'un endpoint dédié - la liste
         des projets de l'utilisateur est déjà bon marché à charger sur
         chaque page et reste minuscule (id/nom/url uniquement).

         JSON_HEX_TAG est requis ici : ce JSON est injecté brut dans un
         <script>, et un nom de projet contenant littéralement "</script>"
         casserait le tag et injecterait du HTML/JS arbitraire (XSS stocké)
         sans cet échappement - JSON_UNESCAPED_SLASHES à lui seul ne protège
         pas contre ça. --}}
    <script type="application/json" id="df-command-palette-data">
        {!! json_encode([
            'projects' => Auth::user()->projects()->orderByDesc('last_activity_at')->get(['id', 'name'])->map(fn ($p) => [
                'label' => $p->name,
                'hint' => 'Projet',
                'url' => route('projects.show', $p),
            ]),
            'global' => [
                ['label' => 'Workspace', 'hint' => 'Aller à', 'url' => route('workspace')],
                ['label' => 'Tous les projets', 'hint' => 'Aller à', 'url' => route('projects.index')],
                ['label' => 'Datasets', 'hint' => 'Aller à', 'url' => route('datasets.index')],
                ['label' => 'Pipelines', 'hint' => 'Aller à', 'url' => route('pipelines.index')],
                ['label' => 'Rapports', 'hint' => 'Aller à', 'url' => route('reports.index')],
                ['label' => 'Historique', 'hint' => 'Aller à', 'url' => route('history.index')],
                ['label' => 'Analytics', 'hint' => 'Aller à', 'url' => route('analytics')],
                ['label' => 'Paramètres', 'hint' => 'Aller à', 'url' => route('settings.index')],
            ],
            'project' => isset($project) ? [
                ['label' => 'Aperçu du projet', 'hint' => $project->name, 'url' => route('projects.show', $project)],
                ['label' => 'Notebook', 'hint' => $project->name, 'url' => route('projects.notebook.show', $project)],
                ['label' => 'Dashboards', 'hint' => $project->name, 'url' => route('projects.dashboards.index', $project)],
                ['label' => 'Relations entre tables', 'hint' => $project->name, 'url' => route('projects.relationships.index', $project)],
                ['label' => 'Rapports', 'hint' => $project->name, 'url' => route('projects.reports.index', $project)],
                ['label' => 'Assistant IA', 'hint' => $project->name, 'url' => route('projects.assistant.index', $project)],
            ] : [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    </script>

    <div class="df-command-palette" data-command-palette hidden>
        <div class="df-command-backdrop" data-command-backdrop></div>
        <div class="df-command-box" role="dialog" aria-modal="true" aria-label="Palette de commandes">
            <input type="text" class="df-command-input" data-command-input placeholder="Rechercher un projet ou une action…" autocomplete="off">
            <ul class="df-command-results" data-command-results></ul>
        </div>
    </div>
</body>
</html>
