@extends('layouts.app')

@section('title', "Visualisations · {$table->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none small text-secondary">&larr; {{ $dataset->name }}</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h5 fw-bold mb-0">Visualisations · {{ $table->name }}</h1>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newVisualizationModal">
        Nouvelle visualisation
    </button>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if ($visualizations->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Aucune visualisation pour le moment.</p>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newVisualizationModal">
            Créer mon premier graphique
        </button>
    </div>
@else
    <div class="row g-3">
        @foreach ($visualizations as $visualization)
            <div class="col-lg-6">
                <div class="df-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 fw-bold mb-0">{{ $visualization->name }}</h2>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('projects.datasets.tables.visualizations.refresh', [$project, $dataset, $table, $visualization]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Actualiser</button>
                            </form>
                            <form method="POST" action="{{ route('projects.datasets.tables.visualizations.destroy', [$project, $dataset, $table, $visualization]) }}" onsubmit="return confirm('Supprimer cette visualisation ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </div>
                    </div>
                    <p class="text-secondary small mb-2">{{ $visualization->chart_type->label() }}</p>
                    @if ($visualization->data_cache)
                        <div style="height: 280px" data-chart-type="{{ $visualization->chart_type->value }}"
                             data-chart-name="{{ $visualization->name }}"
                             data-chart-payload='@json($visualization->data_cache)'></div>
                    @else
                        <p class="text-secondary small mb-0">Pas encore de données.</p>
                    @endif
                    @if ($visualization->rationale)
                        <p class="text-secondary small mt-2 mb-0 fst-italic">💡 {{ $visualization->rationale }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="modal fade" id="newVisualizationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.datasets.tables.visualizations.store', [$project, $dataset, $table]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle visualisation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Type de graphique</label>
                        <select name="chart_type" class="form-select df-dynamic-select" required>
                            <option value="">Choisir...</option>
                            @foreach (\App\Enums\ChartType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="bar,line,scatter">
                        <label class="form-label">Colonne (axe X)</label>
                        <select name="x_column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="bar,line,scatter">
                        <label class="form-label">Colonne (axe Y) {{ '' }}</label>
                        <select name="y_column" class="form-select" disabled>
                            <option value="">— (nombre de lignes)</option>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="pie,donut,radar,treemap">
                        <label class="form-label">Colonne de catégorie</label>
                        <select name="category_column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="pie,donut,treemap">
                        <label class="form-label">Colonne de valeur (optionnel)</label>
                        <select name="value_column" class="form-select" disabled>
                            <option value="">— (nombre de lignes)</option>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="radar">
                        <label class="form-label">Colonnes de valeurs (plusieurs)</label>
                        <select name="value_columns[]" class="form-select" multiple size="5" disabled>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="bar,line,pie,donut,treemap">
                        <label class="form-label">Agrégation</label>
                        <select name="aggregation" class="form-select" disabled>
                            <option value="count">Nombre de lignes</option>
                            <option value="sum">Somme</option>
                            <option value="mean">Moyenne</option>
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="histogram">
                        <label class="form-label">Colonne</label>
                        <select name="column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="histogram">
                        <label class="form-label">Nombre de tranches</label>
                        <input type="number" name="bins" class="form-control" value="10" min="2" max="50" disabled>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="heatmap,boxplot">
                        <label class="form-label">Colonnes {{ '' }}<span class="text-secondary">(vide = toutes les colonnes numériques pour la heatmap)</span></label>
                        <select name="columns[]" class="form-select" multiple size="5" disabled>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
