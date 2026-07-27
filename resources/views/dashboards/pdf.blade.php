<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Dashboard · {{ $dashboard->name }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .subtitle { color: #666; font-size: 12px; margin-bottom: 2px; }
        .meta { color: #888; font-size: 10px; margin-bottom: 14px; }
        .widget-box {
            display: inline-block;
            width: 47%;
            margin: 0 1.5% 14px 1.5%;
            vertical-align: top;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 10px;
        }
        .widget-title { font-size: 12px; font-weight: bold; margin-bottom: 6px; }
        .widget-box img { width: 100%; max-width: 380px; }
        .kpi-value { font-size: 30px; font-weight: bold; text-align: center; margin: 10px 0 2px; }
        .kpi-label { font-size: 11px; color: #666; text-align: center; }
        table.widget-table { width: 100%; border-collapse: collapse; }
        table.widget-table th, table.widget-table td { border: 1px solid #ddd; padding: 3px 6px; font-size: 9.5px; text-align: left; }
        table.widget-table th { background: #f5f5f5; }
        .widget-text { font-size: 11px; }
        .widget-missing { color: #999; font-style: italic; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $dashboard->name }}</h1>
    <p class="subtitle">{{ $project->name }} — export du dashboard</p>
    <p class="meta">Généré le {{ $generatedAt->format('d/m/Y à H:i') }}</p>

    @forelse ($widgets as $entry)
        @php [$widget, $data] = [$entry['widget'], $entry['data']]; @endphp
        <div class="widget-box">
            <div class="widget-title">{{ $widget->title }}</div>

            @if ($widget->widget_type->value === 'chart')
                @if (isset($chartImages[$widget->id]))
                    <img src="data:image/png;base64,{{ $chartImages[$widget->id] }}" alt="{{ $widget->title }}">
                @else
                    <p class="widget-missing">Graphique non disponible.</p>
                @endif
            @elseif ($widget->widget_type->value === 'kpi')
                <div class="kpi-value">{{ $data['value'] !== null ? round($data['value'], 2) : '—' }}</div>
                <div class="kpi-label">{{ $data['label'] }}</div>
            @elseif ($widget->widget_type->value === 'table')
                <table class="widget-table">
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
                <p class="widget-text">{{ $data['content'] }}</p>
            @endif
        </div>
    @empty
        <p class="meta">Ce dashboard n'a aucun widget.</p>
    @endforelse
</body>
</html>
