<?php

namespace App\Enums;

enum WidgetType: string
{
    case Chart = 'chart';
    case Kpi = 'kpi';
    case Text = 'text';
    case Table = 'table';

    public function label(): string
    {
        return match ($this) {
            self::Chart => 'Graphique',
            self::Kpi => 'Indicateur (KPI)',
            self::Text => 'Texte',
            self::Table => 'Résumé de table',
        };
    }
}
