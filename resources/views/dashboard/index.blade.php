@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label">Projets</div>
            <div class="df-stat-value">{{ $stats['projects'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label">Datasets</div>
            <div class="df-stat-value">{{ $stats['datasets'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label">Qualité moyenne</div>
            <div class="df-stat-value">{{ $stats['avg_quality'] !== null ? round($stats['avg_quality']) . '/100' : '—' }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label">Rapports générés</div>
            <div class="df-stat-value">{{ $stats['reports'] }}</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="df-card">
            <h2 class="h6 fw-bold mb-3">Historique récent</h2>

            @if ($recentActivity->isEmpty())
                <p class="text-secondary small mb-0">
                    Aucune activité pour le moment. Créez votre premier projet pour commencer le pipeline d'analyse.
                </p>
            @else
                <ul class="list-unstyled mb-0">
                    @foreach ($recentActivity as $activity)
                        <li class="d-flex justify-content-between py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span class="small">{{ $activity->description }}</span>
                            <span class="small text-secondary">{{ $activity->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="df-card">
            <h2 class="h6 fw-bold mb-3">État du système</h2>

            <div class="d-flex align-items-center gap-2 mb-2">
                @if ($pythonStatus === 'ok')
                    <span class="badge text-bg-success">OK</span>
                    <span class="small">Pont Python opérationnel</span>
                @else
                    <span class="badge text-bg-danger">Erreur</span>
                    <span class="small">Pont Python indisponible</span>
                @endif
            </div>

            @if ($pythonStatus === 'ok')
                <ul class="small text-secondary mb-0 ps-3">
                    <li>Python {{ \Illuminate\Support\Str::before($pythonResult->data['python_version'], ' ') }}</li>
                    @foreach ($pythonResult->data['packages'] as $package => $version)
                        <li>{{ $package }}: {{ $version ?? 'non installé' }}</li>
                    @endforeach
                </ul>
            @else
                <p class="small text-danger mb-0">{{ $pythonResult }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
