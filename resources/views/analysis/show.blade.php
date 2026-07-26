@extends('layouts.app')

@section('title', "Analyse · {$table->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none small text-secondary">&larr; {{ $dataset->name }}</a>
</div>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h5 fw-bold mb-1">Analyse exploratoire · {{ $table->name }}</h1>
        @if ($analysis)
            <p class="text-secondary small mb-0">Générée {{ $analysis->computed_at->diffForHumans() }}</p>
        @endif
    </div>
    <form method="POST" action="{{ route('projects.datasets.tables.analysis.run', [$project, $dataset, $table]) }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">
            {{ $analysis ? "Relancer l'analyse" : "Lancer l'analyse" }}
        </button>
    </form>
</div>

@if ($insights->isNotEmpty())
    <div class="df-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">Insights IA</h2>
            <div class="btn-group btn-group-sm" role="group" data-insight-view-toggle>
                <button type="button" class="btn btn-outline-secondary active" data-insight-view-btn="category">Par catégorie</button>
                <button type="button" class="btn btn-outline-secondary" data-insight-view-btn="priority">Par priorité</button>
            </div>
        </div>

        <div data-insight-view="category">
            <div class="row g-3">
                @foreach (\App\Enums\InsightCategory::ordered() as $category)
                    @php $items = $insights->where('category', $category); @endphp
                    @continue($items->isEmpty())
                    <div class="col-lg-6">
                        <h3 class="small fw-semibold mb-2">{{ $category->icon() }} {{ $category->label() }}</h3>
                        <ul class="small mb-0 ps-3">
                            @foreach ($items as $insight)
                                <li class="mb-1">
                                    {{ $insight->content }}
                                    @if ($insight->importance->value === 'high')
                                        <span class="badge {{ $insight->importance->badgeClass() }} ms-1">{{ $insight->importance->label() }}</span>
                                    @endif
                                    <x-insight-action-button :insight="$insight" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <div data-insight-view="priority" class="d-none">
            <p class="text-secondary small mb-3">Tous les insights, toutes catégories confondues, du plus urgent au moins urgent — pour savoir où regarder en premier.</p>
            @foreach (['high', 'medium', 'low'] as $level)
                @php
                    $items = $insights->filter(fn ($i) => $i->importance->value === $level)
                        ->sortBy(fn ($i) => array_search($i->category, \App\Enums\InsightCategory::ordered(), true));
                @endphp
                @continue($items->isEmpty())
                <h3 class="small fw-semibold mb-2 mt-3">
                    <span class="badge {{ $items->first()->importance->badgeClass() }}">{{ $items->first()->importance->label() }}</span>
                </h3>
                <ul class="small mb-0 ps-3">
                    @foreach ($items as $insight)
                        <li class="mb-1">
                            {{ $insight->category->icon() }} {{ $insight->content }}
                            <x-insight-action-button :insight="$insight" />
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
@endif

@php
    $numericColumns = $table->columns->filter(fn ($c) => $c->detected_type->isNumeric());
    $categoricalColumns = $table->columns->filter(fn ($c) => in_array($c->detected_type->value, ['category', 'boolean'], true));
    $columnsByName = $table->columns->keyBy('name');
@endphp

<div class="df-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Tests statistiques</h2>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newStatisticalTestModal">
            Nouveau test
        </button>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($statisticalTests->isEmpty())
        <p class="text-secondary small mb-0">Aucun test lancé pour le moment. Comparez deux groupes, vérifiez un lien entre deux variables, ou confirmez la significativité d'une corrélation.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr class="small text-secondary">
                        <th>Test</th>
                        <th>Statistique</th>
                        <th>p-value</th>
                        <th>Résultat</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statisticalTests as $test)
                        <tr class="small">
                            <td class="fw-semibold" style="white-space: nowrap">{{ $test->test_type->label() }}</td>
                            <td>{{ $test->result['statistic'] }}</td>
                            <td>{{ $test->result['p_value'] }}</td>
                            <td>
                                <span class="badge {{ $test->result['significant'] ? 'text-bg-success' : 'text-bg-light border' }} mb-1">
                                    {{ $test->result['significant'] ? 'Significatif' : 'Non significatif' }}
                                </span>
                                <div class="text-secondary">{{ $test->result['interpretation'] }}</div>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('projects.datasets.tables.statistical-tests.destroy', [$project, $dataset, $table, $test]) }}" onsubmit="return confirm('Supprimer ce test ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if (! $analysis)
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-0">Aucune analyse disponible pour le moment.</p>
    </div>
@else
    @php $results = $analysis->results; @endphp

    @if (! empty($results['descriptive_stats']))
        <div class="df-card mb-4 p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr class="small text-secondary">
                            <th class="ps-3">Colonne</th>
                            <th>Moyenne</th>
                            <th>Médiane</th>
                            <th>Mode</th>
                            <th>Écart-type</th>
                            <th>Variance</th>
                            <th>Min</th>
                            <th>Q1</th>
                            <th>Q3</th>
                            <th class="pe-3">Max</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results['descriptive_stats'] as $column => $stats)
                            <tr class="small">
                                <td class="ps-3 fw-semibold">
                                    {{ $column }}
                                    @if ($label = $columnsByName->get($column)?->semantic_label)
                                        <div class="small text-secondary fw-normal fst-italic" @if ($columnsByName[$column]->semantic_reasoning) title="{{ $columnsByName[$column]->semantic_reasoning }}" @endif>
                                            {{ $label }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ round($stats['mean'], 2) }}</td>
                                <td>{{ round($stats['median'], 2) }}</td>
                                <td>{{ $stats['mode'] !== null ? round($stats['mode'], 2) : '—' }}</td>
                                <td>{{ round($stats['std'], 2) }}</td>
                                <td>{{ round($stats['variance'], 2) }}</td>
                                <td>{{ round($stats['min'], 2) }}</td>
                                <td>{{ round($stats['q1'], 2) }}</td>
                                <td>{{ round($stats['q3'], 2) }}</td>
                                <td class="pe-3">{{ round($stats['max'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @if (!empty($results['correlation_matrix']['matrix']))
            <div class="col-lg-6">
                <div class="df-card h-100">
                    <h2 class="h6 fw-bold mb-1">Matrice de corrélation</h2>
                    <p class="text-secondary small fst-italic mb-3">💡 Heatmap choisie car plusieurs colonnes numériques existent : elle révèle toutes leurs corrélations en un coup d'œil.</p>
                    <div data-chart-type="heatmap" data-chart-payload='@json($results["correlation_matrix"])'></div>
                </div>
            </div>
        @endif

        @if (!empty($results['boxplots']))
            <div class="col-lg-6">
                <div class="df-card h-100">
                    <h2 class="h6 fw-bold mb-1">Boîtes à moustaches</h2>
                    <p class="text-secondary small fst-italic mb-3">💡 Boîtes à moustaches choisies pour repérer d'un coup d'œil la médiane, la dispersion et les valeurs atypiques de chaque colonne numérique.</p>
                    @php
                        $boxplotSeries = collect($results['boxplots'])
                            ->filter()
                            ->map(fn ($stats, $column) => ['x' => $column, 'y' => [$stats['min'], $stats['q1'], $stats['median'], $stats['q3'], $stats['max']]])
                            ->values();
                    @endphp
                    <div data-chart-type="boxplot" data-chart-payload='@json(["data" => $boxplotSeries])'></div>
                </div>
            </div>
        @endif
    </div>

    @if (!empty($results['histograms']))
        <h2 class="h6 fw-bold mb-1">Histogrammes</h2>
        <p class="text-secondary small fst-italic mb-3">💡 Histogrammes choisis car ces colonnes sont numériques continues : ils montrent comment leurs valeurs se répartissent.</p>
        <div class="row g-3 mb-4">
            @foreach ($results['histograms'] as $column => $histogram)
                @continue(empty($histogram['counts']))
                <div class="col-lg-6">
                    <div class="df-card">
                        <h3 class="small fw-semibold mb-2">{{ $column }}</h3>
                        <div style="height: 220px" data-chart-type="histogram" data-chart-name="{{ $column }}"
                             data-chart-payload='@json($histogram)'></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (!empty($results['distributions']))
        <h2 class="h6 fw-bold mb-1">Distributions (colonnes catégorielles)</h2>
        <p class="text-secondary small fst-italic mb-3">💡 Graphiques en barres choisis car ces colonnes sont catégorielles : ils comparent directement le nombre de lignes par catégorie.</p>
        <div class="row g-3">
            @foreach ($results['distributions'] as $column => $distribution)
                @continue(empty($distribution['counts']))
                <div class="col-lg-6">
                    <div class="df-card">
                        <h3 class="small fw-semibold mb-2">{{ $column }}</h3>
                        <div style="height: 220px" data-chart-type="bar" data-chart-name="{{ $column }}"
                             data-chart-payload='@json(["labels" => $distribution["categories"], "data" => $distribution["counts"]])'></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif

<div class="modal fade" id="newStatisticalTestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.datasets.tables.statistical-tests.store', [$project, $dataset, $table]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau test statistique</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type de test</label>
                        <select name="test_type" class="form-select df-dynamic-select" required>
                            <option value="">Choisir...</option>
                            @foreach (\App\Enums\StatisticalTestType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="t_test,anova">
                        <label class="form-label">Colonne numérique</label>
                        <select name="numeric_column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($numericColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="t_test,anova">
                        <label class="form-label">Colonne de groupe (catégorielle)</label>
                        <select name="group_column" class="form-select df-group-column" disabled>
                            <option value="">—</option>
                            @foreach ($categoricalColumns as $column)
                                <option value="{{ $column->name }}" data-values='@json(array_slice(array_keys($column->stats['top_values'] ?? []), 0, 20))'>{{ $column->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-secondary small mt-1 mb-0">Valeurs disponibles : <span data-group-values-hint>—</span></p>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="t_test">
                        <label class="form-label">Groupe A</label>
                        <input type="text" name="group_a" class="form-control" list="groupValuesList" disabled placeholder="ex : Nord">
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="t_test">
                        <label class="form-label">Groupe B</label>
                        <input type="text" name="group_b" class="form-control" list="groupValuesList" disabled placeholder="ex : Sud">
                    </div>
                    <datalist id="groupValuesList"></datalist>

                    <div class="mb-3 df-field d-none" data-steps="chi_square">
                        <label class="form-label">Colonne A (catégorielle)</label>
                        <select name="column_a" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($categoricalColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="chi_square">
                        <label class="form-label">Colonne B (catégorielle)</label>
                        <select name="column_b" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($categoricalColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 df-field d-none" data-steps="correlation">
                        <label class="form-label">Colonne A (numérique)</label>
                        <select name="column_a" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($numericColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="correlation">
                        <label class="form-label">Colonne B (numérique)</label>
                        <select name="column_b" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($numericColumns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Lancer le test</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
