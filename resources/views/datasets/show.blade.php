@extends('layouts.app')

@section('title', $dataset->name)

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none small text-secondary">&larr; {{ $project->name }}</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h5 fw-bold mb-0">{{ $dataset->name }}</h1>
            <span class="badge {{ $dataset->status->badgeClass() }}">{{ $dataset->status->label() }}</span>
        </div>
        <p class="text-secondary small mb-0">
            {{ $dataset->format->label() }} · {{ number_format($dataset->size_bytes / 1024, 0, ',', ' ') }} Ko ·
            {{ $dataset->format === \App\Enums\DatasetFormat::Derived ? 'créée' : 'importé' }} {{ $dataset->created_at->diffForHumans() }}
        </p>
    </div>

    <div class="d-flex gap-2">
        @unless ($dataset->format === \App\Enums\DatasetFormat::Derived)
            <form method="POST" action="{{ route('projects.datasets.reimport', [$project, $dataset]) }}" onsubmit="return confirm('Retraiter ce fichier depuis le début ? Les tables, colonnes et analyses actuelles seront remplacées.');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Retraiter le fichier</button>
            </form>
        @endunless
        <a href="{{ route('projects.relationships.index', $project) }}" class="btn btn-outline-secondary btn-sm">
            Relations entre tables
        </a>
    </div>
</div>

@forelse ($dataset->tables as $table)
    <div class="df-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">{{ $table->name }}</h2>
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary small">{{ number_format($table->row_count, 0, ',', ' ') }} lignes · {{ $table->column_count }} colonnes</span>
                @if ($table->latestQualityReport)
                    <a href="{{ route('projects.datasets.tables.quality.show', [$project, $dataset, $table]) }}"
                       class="badge text-decoration-none {{ $table->latestQualityReport->grade->badgeClass() }}">
                        Qualité {{ $table->latestQualityReport->score }}/100
                    </a>
                @endif
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#transformModal{{ $table->id }}">
                    Transformer
                </button>
                <form method="POST" action="{{ route('projects.datasets.tables.pipeline-suggestions.store', [$project, $dataset, $table]) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Suggestions IA</button>
                </form>
                <a href="{{ route('projects.datasets.tables.data.show', [$project, $dataset, $table]) }}" class="btn btn-outline-secondary btn-sm">
                    Données
                </a>
                <a href="{{ route('projects.datasets.tables.analysis.show', [$project, $dataset, $table]) }}" class="btn btn-outline-secondary btn-sm">
                    Analyse
                </a>
                <a href="{{ route('projects.datasets.tables.visualizations.index', [$project, $dataset, $table]) }}" class="btn btn-outline-secondary btn-sm">
                    Visualisations
                </a>
                <a href="{{ route('projects.datasets.tables.ml.show', [$project, $dataset, $table]) }}" class="btn btn-outline-secondary btn-sm">
                    ML
                </a>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Exporter
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('projects.datasets.tables.export', [$project, $dataset, $table, 'format' => 'csv']) }}">CSV</a></li>
                        <li><a class="dropdown-item" href="{{ route('projects.datasets.tables.export', [$project, $dataset, $table, 'format' => 'xlsx']) }}">Excel (.xlsx)</a></li>
                        <li><a class="dropdown-item" href="{{ route('projects.datasets.tables.export', [$project, $dataset, $table, 'format' => 'json']) }}">JSON</a></li>
                    </ul>
                </div>
            </div>
        </div>

        @php
            $highlights = $table->aiInsights->whereIn('category', [
                \App\Enums\InsightCategory::Summary,
                \App\Enums\InsightCategory::KeyPoints,
                \App\Enums\InsightCategory::Risk,
                \App\Enums\InsightCategory::Anomaly,
                \App\Enums\InsightCategory::Recommendation,
                \App\Enums\InsightCategory::Visualization,
            ]);
        @endphp
        @if ($highlights->isNotEmpty())
            <div class="df-card bg-body-tertiary mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="small fw-bold mb-0">🤖 Ce que l'IA en retient</h3>
                    <a href="{{ route('projects.datasets.tables.analysis.show', [$project, $dataset, $table]) }}" class="small text-decoration-none">
                        Voir l'analyse complète &rarr;
                    </a>
                </div>
                <ul class="small mb-0 ps-3">
                    @foreach (\App\Enums\InsightCategory::ordered() as $category)
                        @foreach ($highlights->where('category', $category) as $insight)
                            <li class="mb-1">
                                {{ $category->icon() }} {{ $insight->content }}
                                @if ($insight->importance->value === 'high')
                                    <span class="badge {{ $insight->importance->badgeClass() }} ms-1">{{ $insight->importance->label() }}</span>
                                @endif
                                <x-insight-action-button :insight="$insight" />
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($table->pipelineSuggestions()->pending()->exists())
            @php $pendingSuggestions = $table->pipelineSuggestions()->pending()->get(); @endphp
            <div class="df-card bg-body-tertiary mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="small fw-bold mb-0">🧠 Suggestions IA — pipeline proposé</h3>
                    <form method="POST" action="{{ route('projects.datasets.tables.pipeline-suggestions.accept-all', [$project, $dataset, $table]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Tout accepter</button>
                    </form>
                </div>
                <ul class="list-unstyled mb-0 small">
                    @foreach ($pendingSuggestions as $suggestion)
                        <li class="d-flex justify-content-between align-items-start gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div>
                                <div class="fw-semibold">{{ $suggestion->step_type->label() }}</div>
                                <div class="text-secondary fst-italic">💡 {{ $suggestion->rationale }}</div>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <form method="POST" action="{{ route('projects.datasets.tables.pipeline-suggestions.accept', [$project, $dataset, $table, $suggestion]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Accepter</button>
                                </form>
                                <form method="POST" action="{{ route('projects.datasets.tables.pipeline-suggestions.reject', [$project, $dataset, $table, $suggestion]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Rejeter</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-secondary">
                        <th>Colonne</th>
                        <th>Type</th>
                        <th>Valeurs manquantes</th>
                        <th>Distinctes</th>
                        <th>Aperçu</th>
                        <th>Statistiques</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table->columns as $column)
                        <tr>
                            <td class="fw-semibold">
                                {{ $column->name }}
                                @if ($column->is_useless)
                                    <span class="badge text-bg-warning ms-1">peu utile</span>
                                @endif
                                @if ($column->semantic_label)
                                    <div class="small text-secondary fw-normal fst-italic" @if ($column->semantic_reasoning) title="{{ $column->semantic_reasoning }}" @endif>
                                        {{ $column->semantic_label }}
                                    </div>
                                @endif
                            </td>
                            <td><span class="badge text-bg-light border">{{ $column->detected_type->label() }}</span></td>
                            <td class="small">
                                {{ $column->null_count }} ({{ number_format($column->null_percentage, 1) }}%)
                            </td>
                            <td class="small">{{ $column->distinct_count }}</td>
                            <td class="small text-secondary">
                                {{ collect($column->sample_values)->take(3)->map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v)->implode(', ') }}
                            </td>
                            <td class="small text-secondary">
                                @if ($column->detected_type->isNumeric() && isset($column->stats['mean']))
                                    min {{ round($column->stats['min'], 2) }} · moy {{ round($column->stats['mean'], 2) }} · max {{ round($column->stats['max'], 2) }}
                                @elseif (isset($column->stats['top_values']))
                                    @foreach (array_slice($column->stats['top_values'], 0, 3, true) as $value => $count)
                                        <span class="badge text-bg-light border">{{ $value }} ({{ $count }})</span>
                                    @endforeach
                                @elseif (isset($column->stats['min']) && is_string($column->stats['min']))
                                    {{ \Illuminate\Support\Str::limit($column->stats['min'], 10, '') }} → {{ \Illuminate\Support\Str::limit($column->stats['max'], 10, '') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-transform-modal :project="$project" :dataset="$dataset" :table="$table" />
@empty
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-0">Ce dataset n'a pas encore été analysé.</p>
    </div>
@endforelse
@endsection
