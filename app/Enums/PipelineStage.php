<?php

namespace App\Enums;

/**
 * Where a project currently sits in the DataForge pipeline
 * (import -> understanding -> audit -> cleaning -> preprocessing ->
 * transformation -> eda -> visualization -> dashboard -> report -> export).
 * The user can always jump back to an earlier step.
 */
enum PipelineStage: string
{
    case Import = 'import';
    case Understanding = 'understanding';
    case Audit = 'audit';
    case Cleaning = 'cleaning';
    case Preprocessing = 'preprocessing';
    case Transformation = 'transformation';
    case Eda = 'eda';
    case Visualization = 'visualization';
    case Dashboard = 'dashboard';
    case Report = 'report';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Importation',
            self::Understanding => 'Compréhension',
            self::Audit => 'Audit qualité',
            self::Cleaning => 'Nettoyage',
            self::Preprocessing => 'Prétraitement',
            self::Transformation => 'Transformation',
            self::Eda => 'Analyse exploratoire',
            self::Visualization => 'Visualisations',
            self::Dashboard => 'Dashboard',
            self::Report => 'Rapport',
            self::Export => 'Export',
        };
    }

    /** @return array<self> Ordered pipeline steps, for rendering the stepper. */
    public static function ordered(): array
    {
        return [
            self::Import, self::Understanding, self::Audit, self::Cleaning,
            self::Preprocessing, self::Transformation, self::Eda,
            self::Visualization, self::Dashboard, self::Report, self::Export,
        ];
    }
}
