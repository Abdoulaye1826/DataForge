@extends('layouts.app')

@section('title', "Qualité · {$table->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none small text-secondary">
        &larr; {{ $dataset->name }}
    </a>
</div>

@php $report = $table->latestQualityReport; @endphp

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h5 fw-bold mb-1">Audit qualité · {{ $table->name }}</h1>
        <p class="text-secondary small mb-0">
            {{ number_format($table->row_count, 0, ',', ' ') }} lignes · {{ $table->column_count }} colonnes
            @if ($report)
                · généré {{ $report->generated_at->diffForHumans() }}
            @endif
        </p>
    </div>
    <form method="POST" action="{{ route('projects.datasets.tables.quality.refresh', [$project, $dataset, $table]) }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm">Régénérer l'audit</button>
    </form>
</div>

@if (! $report)
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-0">Aucun audit qualité disponible pour cette table.</p>
    </div>
@else
    @if ($report->narrative)
        <div class="df-card mb-4">
            <p class="mb-0">🩺 {{ $report->narrative }}</p>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="df-card text-center">
                <div class="df-stat-label">Score qualité</div>
                <div class="df-stat-value">{{ $report->score }}<span class="fs-6 text-secondary">/100</span></div>
                <span class="badge {{ $report->grade->badgeClass() }} mt-2">{{ $report->grade->label() }}</span>
            </div>
        </div>
        <div class="col-md-8">
            <div class="df-card h-100">
                <div class="row text-center g-3">
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold">{{ $report->summary['duplicate_rows'] }}</div>
                        <div class="text-secondary small">Doublons</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold">{{ $report->summary['useless_columns_count'] }}</div>
                        <div class="text-secondary small">Colonnes peu utiles</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold">{{ $report->summary['highly_correlated_pairs_count'] }}</div>
                        <div class="text-secondary small">Paires corrélées</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold">{{ $report->summary['avg_null_percentage'] }}%</div>
                        <div class="text-secondary small">Nulls (moyenne)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="df-card h-100">
                <h2 class="h6 fw-bold mb-3">Colonnes peu utiles</h2>
                @if (empty($report->details['useless_columns']))
                    <p class="text-secondary small mb-0">Aucune colonne constante ou quasi-vide détectée.</p>
                @else
                    <ul class="mb-0 small">
                        @foreach ($report->details['useless_columns'] as $column)
                            <li>{{ $column }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <div class="df-card h-100">
                <h2 class="h6 fw-bold mb-3">Colonnes fortement corrélées</h2>
                @if (empty($report->details['highly_correlated_pairs']))
                    <p class="text-secondary small mb-0">Aucune corrélation forte détectée.</p>
                @else
                    <ul class="mb-0 small">
                        @foreach ($report->details['highly_correlated_pairs'] as $pair)
                            <li>{{ $pair['column_a'] }} ↔ {{ $pair['column_b'] }} <span class="text-secondary">(r = {{ $pair['correlation'] }})</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="col-12">
            <div class="df-card">
                <h2 class="h6 fw-bold mb-3">Valeurs aberrantes (méthode IQR)</h2>
                @if (empty($report->details['outliers']))
                    <p class="text-secondary small mb-0">Aucune valeur aberrante détectée sur les colonnes numériques.</p>
                @else
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr class="small text-secondary">
                                <th>Colonne</th>
                                <th>Nombre</th>
                                <th>Ratio</th>
                                <th>Bornes attendues</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report->details['outliers'] as $outlier)
                                <tr class="small">
                                    <td>{{ $outlier['column'] }}</td>
                                    <td>{{ $outlier['count'] }}</td>
                                    <td>{{ number_format($outlier['ratio'] * 100, 1) }}%</td>
                                    <td>[{{ $outlier['lower_bound'] }}, {{ $outlier['upper_bound'] }}]</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endif
@endsection
