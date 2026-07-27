@extends('layouts.app')

@section('title', 'Workspace')

@section('content')
<div class="mb-4">
    <h1 class="h4 fw-bold mb-1">Bonjour, {{ auth()->user()->name }} 👋</h1>
    <p class="text-secondary mb-0">Prêt à transformer vos données en décisions ?</p>
</div>

@if ($hasAnyProject)
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="{{ route('projects.index') }}" class="df-quick-action primary">
                <div class="df-qa-icon">＋</div>
                <div class="df-qa-title">Nouveau projet</div>
                <div class="df-qa-sub">Créer une nouvelle analyse</div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('projects.show', $continueProject) }}" class="df-quick-action">
                <div class="df-qa-icon">▣</div>
                <div class="df-qa-title">Continuer un projet</div>
                <div class="df-qa-sub">Reprendre « {{ \Illuminate\Support\Str::limit($continueProject->name, 24) }} »</div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('projects.show', $continueProject) }}" class="df-quick-action">
                <div class="df-qa-icon">⇧</div>
                <div class="df-qa-title">Importer un dataset</div>
                <div class="df-qa-sub">Démarrer immédiatement</div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('projects.assistant.index', $continueProject) }}" class="df-quick-action">
                <div class="df-qa-icon">✦</div>
                <div class="df-qa-title">Assistant IA</div>
                <div class="df-qa-sub">Poser une question</div>
            </a>
        </div>
    </div>
@else
    <div class="df-card mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Bienvenue sur {{ config('app.name') }}</h2>
            <p class="text-secondary small mb-0">
                Créez votre premier projet pour importer un jeu de données et démarrer le pipeline d'analyse automatique.
            </p>
        </div>
        <a href="{{ route('projects.index') }}" class="btn btn-primary text-nowrap">
            <span class="df-ic">▣</span> Créer mon premier projet
        </a>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h2 class="h6 fw-bold mb-0">Projets récents</h2>
            <a href="{{ route('projects.index') }}" class="small">Tous les projets →</a>
        </div>

        @if ($recentProjects->isEmpty())
            <div class="df-card mb-4">
                <p class="text-secondary small mb-0">Aucun projet pour le moment.</p>
            </div>
        @else
            <div class="row g-2 mb-4">
                @foreach ($recentProjects as $recentProject)
                    @php
                        $progress = $recentProject->pipelineProgress();
                        $done = count(array_filter($progress));
                        $percent = (int) round(($done / count($progress)) * 100);
                    @endphp
                    <div class="col-md-6">
                        <a href="{{ route('projects.show', $recentProject) }}" class="df-mini-project">
                            <div class="df-mp-top">
                                <span class="df-mp-name">{{ \Illuminate\Support\Str::limit($recentProject->name, 26) }}</span>
                                <span class="badge {{ $recentProject->status->badgeClass() }}">{{ $recentProject->status->label() }}</span>
                            </div>
                            <div class="df-mp-meta">Modifié {{ $recentProject->last_activity_at?->diffForHumans() ?? '—' }}</div>
                            <div class="df-mp-bar"><span style="width: {{ $percent }}%"></span></div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        <h2 class="h6 fw-bold mb-2">Activité récente</h2>
        <div class="df-card">
            @if ($recentActivity->isEmpty())
                <p class="text-secondary small mb-0">Aucune activité pour le moment.</p>
            @else
                <ul class="list-unstyled mb-0">
                    @foreach ($recentActivity as $activity)
                        <li class="d-flex justify-content-between gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span class="small">{{ $activity->description }}</span>
                            <span class="small text-secondary text-nowrap">{{ $activity->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <h2 class="h6 fw-bold mb-2">Suggestions intelligentes</h2>
        @if (empty($feed))
            <div class="df-card">
                <p class="text-secondary small mb-0">
                    Rien à signaler pour l'instant. Les suggestions apparaîtront ici dès que l'IA détecte une action utile.
                </p>
            </div>
        @else
            <div class="d-flex flex-column gap-2">
                @foreach ($feed as $item)
                    <a href="{{ $item['url'] }}" class="df-feed-item {{ $item['severity'] }}">
                        <div class="df-feed-dot">{{ $item['icon'] }}</div>
                        <div class="flex-fill">
                            <div class="df-feed-msg">{{ $item['message'] }}</div>
                            <div class="df-feed-src">{{ $item['source'] }} · {{ $item['action_label'] }} →</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
