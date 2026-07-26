@extends('layouts.app')

@section('title', "Machine Learning · {$table->name}")

@section('content')
@php
    $numericColumns = $table->columns->filter(fn ($c) => $c->detected_type->isNumeric());
    $allColumns = $table->columns;
@endphp

<div class="mb-3">
    <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none small text-secondary">&larr; {{ $dataset->name }}</a>
</div>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h5 fw-bold mb-1">Machine Learning · {{ $table->name }}</h1>
        <p class="text-secondary small mb-0">Segmentation (clustering) et prévision de tendance, via scikit-learn.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newMlAnalysisModal">
        Nouvelle analyse
    </button>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if ($analyses->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Aucune analyse pour le moment.</p>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newMlAnalysisModal">
            Lancer ma première analyse
        </button>
    </div>
@else
    <div class="row g-3">
        @foreach ($analyses as $analysis)
            <div class="col-lg-6">
                <div class="df-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 fw-bold mb-0">{{ $analysis->analysis_type->label() }}</h2>
                        <form method="POST" action="{{ route('projects.datasets.tables.ml.destroy', [$project, $dataset, $table, $analysis]) }}" onsubmit="return confirm('Supprimer cette analyse ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                        </form>
                    </div>
                    <p class="text-secondary small mb-2">{{ $analysis->computed_at->diffForHumans() }}</p>

                    @if ($analysis->analysis_type->value === 'clustering')
                        @php $result = $analysis->result; @endphp
                        <p class="small mb-2">
                            {{ $result['n_clusters'] }} clusters sur {{ implode(', ', $result['columns']) }} · inertie {{ $result['inertia'] }}
                        </p>
                        <div style="height: 260px" data-chart-type="scatter" data-chart-name="Clusters"
                             data-chart-payload='@json($result["scatter"])'></div>
                        <table class="table table-sm small mt-2 mb-0">
                            <thead><tr><th>Cluster</th><th>Taille</th>@foreach ($result['columns'] as $c)<th>{{ $c }} (moy.)</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($result['cluster_sizes'] as $clusterId => $size)
                                    <tr>
                                        <td>{{ $clusterId }}</td>
                                        <td>{{ $size }}</td>
                                        @foreach ($result['columns'] as $c)
                                            <td>{{ $result['cluster_means'][$clusterId][$c] ?? '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        @php $result = $analysis->result; @endphp
                        <p class="small mb-2">
                            Tendance : <span class="badge {{ $result['trend'] === 'hausse' ? 'text-bg-success' : ($result['trend'] === 'baisse' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ $result['trend'] }}</span>
                            pente {{ $result['slope'] }} · R² {{ $result['r2'] }}
                        </p>
                        <div style="height: 260px" data-chart-type="line" data-chart-name="Prévision"
                             data-chart-payload='@json($result)'></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="modal fade" id="newMlAnalysisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.datasets.tables.ml.store', [$project, $dataset, $table]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle analyse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type d'analyse</label>
                        <select name="analysis_type" class="form-select df-dynamic-select" required>
                            <option value="">Choisir...</option>
                            @foreach (\App\Enums\MlAnalysisType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="clustering">
                        <label class="form-label">Colonnes numériques (2 ou plus)</label>
                        <select name="columns[]" class="form-select" multiple size="6" disabled>
                            @foreach ($numericColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="clustering">
                        <label class="form-label">Nombre de clusters</label>
                        <input type="number" name="n_clusters" class="form-control" value="3" min="2" max="10" disabled>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="forecast">
                        <label class="form-label">Colonne de période (date ou numéro séquentiel)</label>
                        <select name="date_column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($allColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="forecast">
                        <label class="form-label">Colonne à prévoir (numérique)</label>
                        <select name="value_column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($numericColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="forecast">
                        <label class="form-label">Nombre de périodes à prévoir</label>
                        <input type="number" name="periods" class="form-control" value="4" min="1" max="24" disabled>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Lancer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
