@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
@if ($pythonError)
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
        <span class="df-ic">⚠</span>
        <div>
            <strong>Pont Python indisponible.</strong>
            <span class="small d-block text-secondary">{{ $pythonError }}</span>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label"><span class="df-ic">▣</span> Projets</div>
            <div class="df-stat-value">{{ $stats['projects'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label"><span class="df-ic">◆</span> Datasets</div>
            <div class="df-stat-value">{{ $stats['datasets'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label"><span class="df-ic">✦</span> Qualité moyenne</div>
            <div class="df-stat-value">{{ $stats['avg_quality'] !== null ? round($stats['avg_quality']) . '/100' : '—' }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-tile">
            <div class="df-stat-label"><span class="df-ic">▥</span> Rapports générés</div>
            <div class="df-stat-value">{{ $stats['reports'] }}</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="df-card">
            <h2 class="h6 fw-bold mb-3">Activité (14 derniers jours)</h2>

            @php $activityTrendPayload = $activityTrend; @endphp
            <div style="height: 220px" data-chart-type="line" data-chart-name="Activité"
                 data-chart-payload='@json($activityTrendPayload)'></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="df-card">
            <h2 class="h6 fw-bold mb-3">Historique récent</h2>

            @if ($recentActivity->isEmpty())
                <p class="text-secondary small mb-0">
                    Aucune activité pour le moment. Créez votre premier projet pour commencer le pipeline d'analyse.
                </p>
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
</div>
@endsection
