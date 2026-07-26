<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport · {{ $project->name }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin: 0 0 2px 0; padding-top: 18px; border-top: 1px solid #ddd; }
        h3 { font-size: 12px; margin: 10px 0 4px 0; }
        .subtitle { color: #666; font-size: 12px; margin-bottom: 8px; }
        .meta { color: #888; font-size: 10px; }
        .business-context { font-size: 11px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.stats th, table.stats td { border: 1px solid #ddd; padding: 3px 6px; font-size: 9.5px; text-align: right; }
        table.stats th:first-child, table.stats td:first-child { text-align: left; }
        table.stats th { background: #f5f5f5; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9.5px; color: #fff; }
        .badge-good { background: #2e7d32; }
        .badge-medium { background: #ef6c00; }
        .badge-bad { background: #c62828; }
        .badge-high { background: #c62828; }
        .badge-medium-sev { background: #0288d1; }
        .badge-low { background: #999; }
        ul.insights { margin: 0 0 8px 0; padding-left: 16px; }
        ul.insights li { margin-bottom: 3px; }
        ol.steps { margin: 0 0 8px 0; padding-left: 16px; }
        ol.steps li { margin-bottom: 4px; }
        .step-rationale { color: #666; font-style: italic; }
        .charts { width: 100%; }
        .chart-box { display: inline-block; width: 48%; margin: 4px 1%; text-align: center; vertical-align: top; }
        .chart-box img { width: 100%; max-width: 320px; }
        .chart-caption { font-size: 9.5px; color: #666; }
        .chart-rationale { font-size: 9px; color: #888; font-style: italic; }
        .conclusion-box { background: #f5f5f5; padding: 10px 12px; margin-top: 14px; border-left: 3px solid #444; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>{{ $project->name }}</h1>
    <p class="subtitle">Rapport d'analyse généré par DataForge</p>
    <p class="meta">Généré le {{ $generatedAt->format('d/m/Y à H:i') }}</p>

    {{-- 1. Contexte & Objectif --}}
    @if ($project->businessContextLine() || $project->description)
        <h2>Contexte &amp; objectif</h2>
        @if ($project->businessContextLine())
            <p class="business-context">{{ $project->businessContextLine() }}</p>
        @endif
        @if ($project->description)
            <p>{{ $project->description }}</p>
        @endif
    @endif

    {{-- 2. Qualité agrégée --}}
    @if ($qualityOverview['tables']->isNotEmpty())
        <h2>Qualité des données</h2>
        @if ($qualityOverview['average_score'] !== null)
            <p class="meta">
                Score qualité moyen sur {{ $qualityOverview['tables']->count() }} table(s) :
                <span class="badge {{ $qualityOverview['average_score'] >= 80 ? 'badge-good' : ($qualityOverview['average_score'] >= 50 ? 'badge-medium' : 'badge-bad') }}">
                    {{ $qualityOverview['average_score'] }}/100
                </span>
            </p>
        @endif
        @foreach ($qualityOverview['tables'] as $row)
            <h3>{{ $row['table_name'] }} — {{ $row['report']->score }}/100 ({{ $row['report']->grade->label() }})</h3>
            @if ($row['report']->narrative)
                <p>{{ $row['report']->narrative }}</p>
            @endif
        @endforeach
    @endif

    {{-- 3. Préparation (pipeline justifié) --}}
    @if ($preparation->isNotEmpty())
        <h2>Préparation des données</h2>
        <ol class="steps">
            @foreach ($preparation as $step)
                <li>
                    {{ $step->label }}
                    @if ($step->rationale)
                        <div class="step-rationale">{{ $step->rationale }}</div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    {{-- 4. Résultats & Visualisations --}}
    @if (!empty($resultsSections))
        <h2>Résultats &amp; visualisations</h2>
        @foreach ($resultsSections as $section)
            <h3>{{ $section['table_name'] }}</h3>
            <p class="meta">
                Fichier {{ $section['dataset_name'] }} · {{ number_format($section['row_count'], 0, ',', ' ') }} lignes · {{ $section['column_count'] }} colonnes
            </p>

            @if ($section['analysis'] && ! empty($section['analysis']->results['descriptive_stats']))
                <table class="stats">
                    <thead>
                        <tr>
                            <th>Colonne</th><th>Moyenne</th><th>Médiane</th><th>Écart-type</th><th>Min</th><th>Max</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['analysis']->results['descriptive_stats'] as $column => $stats)
                            @continue(empty($stats))
                            <tr>
                                <td>{{ $column }}</td>
                                <td>{{ round($stats['mean'], 2) }}</td>
                                <td>{{ round($stats['median'], 2) }}</td>
                                <td>{{ round($stats['std'], 2) }}</td>
                                <td>{{ round($stats['min'], 2) }}</td>
                                <td>{{ round($stats['max'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($section['charts']->isNotEmpty())
                <div class="charts">
                    @foreach ($section['charts'] as $chart)
                        <div class="chart-box">
                            <img src="data:image/png;base64,{{ $chart['base64'] }}" alt="{{ $chart['name'] }}">
                            <div class="chart-caption">{{ $chart['name'] }}</div>
                            @if ($chart['rationale'])
                                <div class="chart-rationale">{{ $chart['rationale'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif

    {{-- 5. Insights triés par sévérité --}}
    @if ($insightsBySeverity->isNotEmpty())
        <h2>Insights — du plus urgent au moins urgent</h2>
        @foreach ($insightsBySeverity as $group)
            <h3>
                <span class="badge {{ $group['level'] === 'high' ? 'badge-high' : ($group['level'] === 'medium' ? 'badge-medium-sev' : 'badge-low') }}">
                    {{ \App\Enums\InsightImportance::from($group['level'])->label() }}
                </span>
            </h3>
            <ul class="insights">
                @foreach ($group['items'] as $insight)
                    <li>{{ $insight->category->icon() }} {{ $insight->content }}</li>
                @endforeach
            </ul>
        @endforeach
    @endif

    {{-- 6. Recommandations agrégées --}}
    @if ($recommendations->isNotEmpty())
        <h2>Recommandations</h2>
        <ul class="insights">
            @foreach ($recommendations as $insight)
                <li>{{ $insight->content }}</li>
            @endforeach
        </ul>
    @endif

    {{-- 7. Conclusion --}}
    <h2>Conclusion</h2>
    @if ($conclusion)
        <p class="conclusion-box">{{ $conclusion }}</p>
    @else
        <p class="meta">Synthèse indisponible pour le moment.</p>
    @endif
</body>
</html>
