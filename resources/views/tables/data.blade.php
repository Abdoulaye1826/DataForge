@extends('layouts.app')

@section('title', "Données · {$table->name}")

@section('content')
@php
    $suggestedColumns = $suggestions->flatMap->referencedColumns()->unique()->all();
    $suggestionsForJs = $suggestions->map(function ($s) use ($project, $dataset, $table) {
        return [
            'id' => $s->id,
            'step_type_label' => $s->step_type->label(),
            'rationale' => $s->rationale,
            'columns' => $s->referencedColumns(),
            'accept_url' => route('projects.datasets.tables.pipeline-suggestions.accept', [$project, $dataset, $table, $s]),
            'reject_url' => route('projects.datasets.tables.pipeline-suggestions.reject', [$project, $dataset, $table, $s]),
        ];
    });
@endphp

<script type="application/json" id="df-column-suggestions-data">
    @json($suggestionsForJs)
</script>

<div class="mb-3">
    <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none small text-secondary">&larr; {{ $dataset->name }}</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 fw-bold mb-1">Données · {{ $table->name }}</h1>
        <p class="text-secondary small mb-0">Aperçu ligne par ligne des données brutes.</p>
    </div>
    <input type="search" class="form-control" style="max-width: 280px" placeholder="Rechercher..." data-grid-search>
</div>

<div class="df-card p-0"
     id="table-browser"
     data-rows-url="{{ route('projects.datasets.tables.data.rows', [$project, $dataset, $table]) }}"
     data-total="{{ $page['total'] }}"
     data-page="{{ $page['page'] }}"
     data-per-page="{{ $page['per_page'] }}">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary" data-grid-head>
                    @foreach ($page['columns'] as $column)
                        @php
                            $columnModel = $columnsByName[$column] ?? null;
                            $currentType = $columnModel?->current_type ?? $columnModel?->detected_type;
                        @endphp
                        <th class="ps-3 user-select-none" style="white-space: nowrap">
                            <span class="d-inline-flex align-items-center gap-1">
                                <span style="cursor: pointer" data-grid-sort="{{ $column }}">
                                    {{ $column }} <span class="data-grid-sort-arrow"></span>
                                </span>

                                {{-- Module type par colonne (inspiré de Power Query) : le badge affiche
                                     le type actuel, et son menu permet de le convertir directement sans
                                     passer par la modale de transformation générale. --}}
                                <div class="dropdown">
                                    <button type="button" class="df-column-type-badge" data-bs-toggle="dropdown"
                                            data-bs-strategy="fixed" aria-expanded="false" title="Changer le type">
                                        {{ $currentType?->label() ?? '?' }}
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-sm small">
                                        <li><h6 class="dropdown-header">Convertir en...</h6></li>
                                        @foreach (['integer', 'float', 'string', 'date', 'datetime', 'boolean'] as $targetType)
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item {{ $currentType?->value === $targetType ? 'active' : '' }}"
                                                        data-quick-convert-column="{{ $column }}"
                                                        data-quick-convert-target="{{ $targetType }}">
                                                    {{ \App\Enums\ColumnType::from($targetType)->label() }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <button type="button" class="df-column-configure-btn" data-column-configure="{{ $column }}"
                                        title="Configurer cette colonne">
                                    ⚙
                                    @if (in_array($column, $suggestedColumns, true))
                                        <span class="df-column-suggestion-dot" title="Suggestion IA disponible"></span>
                                    @endif
                                </button>
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody data-grid-body>
                @forelse ($page['rows'] as $row)
                    <tr>
                        @foreach ($page['columns'] as $column)
                            <td class="ps-3 small">{{ $row[$column] === null ? '—' : (is_bool($row[$column]) ? ($row[$column] ? 'true' : 'false') : $row[$column]) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="ps-3 small text-secondary" colspan="{{ count($page['columns']) }}">Aucune ligne.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-3 border-top small text-secondary">
        <span data-grid-count></span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-grid-prev>&laquo; Précédent</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-grid-next>Suivant &raquo;</button>
        </div>
    </div>
</div>

<x-transform-modal :project="$project" :dataset="$dataset" :table="$table" />
@endsection
