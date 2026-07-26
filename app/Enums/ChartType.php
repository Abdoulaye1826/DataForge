<?php

namespace App\Enums;

enum ChartType: string
{
    case Bar = 'bar';
    case Line = 'line';
    case Pie = 'pie';
    case Donut = 'donut';
    case Scatter = 'scatter';
    case Histogram = 'histogram';
    case Heatmap = 'heatmap';
    case Radar = 'radar';
    case Treemap = 'treemap';
    case Boxplot = 'boxplot';

    public function label(): string
    {
        return match ($this) {
            self::Bar => 'Barres',
            self::Line => 'Courbe',
            self::Pie => 'Camembert',
            self::Donut => 'Anneau',
            self::Scatter => 'Nuage de points',
            self::Histogram => 'Histogramme',
            self::Heatmap => 'Carte de chaleur (corrélations)',
            self::Radar => 'Radar',
            self::Treemap => 'Treemap',
            self::Boxplot => 'Boîtes à moustaches',
        };
    }

    /** Charts rendered with ApexCharts - Chart.js has no built-in support for these. */
    public function usesApexCharts(): bool
    {
        return in_array($this, [self::Heatmap, self::Treemap, self::Boxplot], true);
    }
}
