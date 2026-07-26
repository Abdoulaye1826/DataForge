@extends('layouts.app')

@section('title', $dashboard->name)

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.dashboards.index', $project) }}" class="text-decoration-none small text-secondary">&larr; Dashboards</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h5 fw-bold mb-0">{{ $dashboard->name }}</h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWidgetModal">
            Ajouter un widget
        </button>
        <form method="POST" action="{{ route('projects.dashboards.duplicate', [$project, $dashboard]) }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Dupliquer</button>
        </form>
        <form method="POST" action="{{ route('projects.dashboards.destroy', [$project, $dashboard]) }}" onsubmit="return confirm('Supprimer ce dashboard ?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer</button>
        </form>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if ($widgets->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Ce dashboard n'a pas encore de widget.</p>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWidgetModal">
            Ajouter mon premier widget
        </button>
    </div>
@else
    @if (!empty($filterableColumns))
        <div class="df-card mb-3" id="dashboard-filter" data-columns='@json($filterableColumns)'>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="small fw-semibold mb-0">Filtre :</label>
                <select class="form-select form-select-sm" style="max-width: 200px" data-filter-column>
                    <option value="">Aucun</option>
                    @foreach ($filterableColumns as $column)
                        <option value="{{ $column['name'] }}">{{ $column['name'] }}</option>
                    @endforeach
                </select>
                <select class="form-select form-select-sm d-none" style="max-width: 200px" data-filter-value></select>
                <input type="date" class="form-control form-control-sm d-none" style="max-width: 160px" data-filter-start>
                <span class="small text-secondary d-none" data-filter-date-sep>&rarr;</span>
                <input type="date" class="form-control form-control-sm d-none" style="max-width: 160px" data-filter-end>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-filter-reset>Réinitialiser</button>
                <span class="small text-secondary" data-filter-status></span>
            </div>
        </div>
    @endif

    <div class="grid-stack" id="dashboard-grid">
        @foreach ($widgets as $entry)
            @php [$widget, $data] = [$entry['widget'], $entry['data']]; @endphp
            <div class="grid-stack-item"
                 gs-x="{{ $widget->position_x }}" gs-y="{{ $widget->position_y }}"
                 gs-w="{{ $widget->width }}" gs-h="{{ $widget->height }}"
                 data-widget-id="{{ $widget->id }}"
                 data-update-url="{{ route('projects.dashboards.widgets.update', [$project, $dashboard, $widget]) }}"
                 @if ($widget->widget_type->value === 'chart' && $widget->visualization)
                     data-widget-table-id="{{ $widget->visualization->dataset_table_id }}"
                     data-widget-data-url="{{ route('projects.dashboards.widgets.data', [$project, $dashboard, $widget]) }}"
                 @endif>
                <div class="grid-stack-item-content df-card d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="small fw-bold mb-0">{{ $widget->title }}</h2>
                        <form method="POST" action="{{ route('projects.dashboards.widgets.destroy', [$project, $dashboard, $widget]) }}" onsubmit="return confirm('Supprimer ce widget ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1">&times;</button>
                        </form>
                    </div>

                    <div class="flex-grow-1" style="min-height: 0; overflow: auto;">
                        @if ($widget->widget_type->value === 'chart')
                            @if ($data['chart_type'])
                                <div style="height: 100%; min-height: 150px;" data-chart-type="{{ $data['chart_type'] }}"
                                     data-chart-name="{{ $widget->title }}" data-chart-payload='@json($data['data'])'></div>
                            @else
                                <p class="text-secondary small mb-0">Visualisation introuvable.</p>
                            @endif
                        @elseif ($widget->widget_type->value === 'kpi')
                            <div class="text-center py-3">
                                <div class="df-stat-value">{{ $data['value'] !== null ? round($data['value'], 2) : '—' }}</div>
                                <div class="df-stat-label">{{ $data['label'] }}</div>
                            </div>
                        @elseif ($widget->widget_type->value === 'table')
                            <table class="table table-sm small mb-0">
                                <thead><tr><th>Colonne</th><th>Type</th><th>Nulls</th></tr></thead>
                                <tbody>
                                    @foreach ($data['columns'] as $column)
                                        <tr>
                                            <td>{{ $column->name }}</td>
                                            <td>{{ $column->detected_type->label() }}</td>
                                            <td>{{ number_format($column->null_percentage, 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="small mb-0">{{ $data['content'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="modal fade" id="addWidgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.dashboards.widgets.store', [$project, $dashboard]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un widget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type de widget</label>
                        <select name="widget_type" class="form-select df-dynamic-select" required>
                            <option value="">Choisir...</option>
                            <option value="chart">Graphique (à partir d'une visualisation existante)</option>
                            <option value="kpi">Indicateur (KPI)</option>
                            <option value="table">Résumé de table</option>
                            <option value="text">Texte</option>
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="chart">
                        <label class="form-label">Visualisation</label>
                        <select name="visualization_id" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($visualizations as $visualization)
                                <option value="{{ $visualization->id }}">{{ $visualization->name }} ({{ $visualization->table->name }})</option>
                            @endforeach
                        </select>
                        @if ($visualizations->isEmpty())
                            <div class="form-text text-warning">Aucune visualisation dans ce projet - créez-en une depuis une table.</div>
                        @endif
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="kpi">
                        <label class="form-label">Table</label>
                        <select name="kpi_table_id" class="form-select df-kpi-table" disabled>
                            <option value="">—</option>
                            @foreach ($tables as $table)
                                <option value="{{ $table->id }}">{{ $table->dataset->name }} · {{ $table->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="kpi">
                        <label class="form-label">Colonne</label>
                        <select name="kpi_column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($tables as $table)
                                @foreach ($table->columns as $column)
                                    <option value="{{ $column->name }}" data-table-id="{{ $table->id }}" class="df-kpi-column-option d-none">{{ $column->name }} ({{ $table->name }})</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="kpi">
                        <label class="form-label">Statistique</label>
                        <select name="stat" class="form-select" disabled>
                            <option value="mean">Moyenne</option>
                            <option value="median">Médiane</option>
                            <option value="min">Min</option>
                            <option value="max">Max</option>
                            <option value="std">Écart-type</option>
                            <option value="distinct_count">Valeurs distinctes</option>
                            <option value="null_count">Valeurs manquantes</option>
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="kpi,table,text">
                        <label class="form-label">Titre du widget</label>
                        <input type="text" name="label" class="form-control" disabled>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="table">
                        <label class="form-label">Table</label>
                        <select name="table_table_id" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($tables as $table)
                                <option value="{{ $table->id }}">{{ $table->dataset->name }} · {{ $table->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="text">
                        <label class="form-label">Contenu</label>
                        <textarea name="content" class="form-control" rows="4" disabled></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
