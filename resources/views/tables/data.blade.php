@extends('layouts.app')

@section('title', "Données · {$table->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none small text-secondary">&larr; {{ $dataset->name }}</a>
</div>

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
                        <th class="ps-3 user-select-none" style="cursor: pointer; white-space: nowrap" data-grid-sort="{{ $column }}">
                            {{ $column }} <span class="data-grid-sort-arrow"></span>
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
@endsection
