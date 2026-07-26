<?php

namespace App\Enums;

enum MlAnalysisType: string
{
    case Clustering = 'clustering';
    case Forecast = 'forecast';

    public function label(): string
    {
        return match ($this) {
            self::Clustering => 'Segmentation (clustering)',
            self::Forecast => 'Prévision (tendance)',
        };
    }

    /** @return string[] Config keys the form must submit for this analysis type. */
    public function requiredFields(): array
    {
        return match ($this) {
            self::Clustering => ['columns', 'n_clusters'],
            self::Forecast => ['date_column', 'value_column', 'periods'],
        };
    }
}
