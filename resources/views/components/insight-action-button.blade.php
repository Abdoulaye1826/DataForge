@props(['insight'])

@php
    $action = $insight->suggested_action;
@endphp

@if ($action)
    @php
        $table = $insight->table;
        $dataset = $table->dataset;
        $project = $insight->project;

        [$url, $label] = match ($action['type']) {
            'forecast' => [route('projects.datasets.tables.ml.show', [$project, $dataset, $table]), 'Prévoir →'],
            'statistical_test' => [route('projects.datasets.tables.analysis.show', [$project, $dataset, $table]), 'Tester →'],
            'visualization' => [route('projects.datasets.tables.visualizations.index', [$project, $dataset, $table]), 'Visualiser →'],
            'cleaning_step' => [route('projects.datasets.show', [$project, $dataset]), 'Nettoyer →'],
            default => [null, null],
        };
    @endphp

    @if ($url)
        <a
            href="{{ $url }}?prefill_type={{ $action['type'] }}&prefill_table_id={{ $table->id }}&prefill_params={{ urlencode(json_encode($action['params'])) }}"
            class="btn btn-outline-primary btn-sm ms-1"
        >{{ $label }}</a>
    @endif
@endif
