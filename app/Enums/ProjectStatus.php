<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Importing = 'importing';
    case Cleaning = 'cleaning';
    case Analyzing = 'analyzing';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Importing => 'Importation',
            self::Cleaning => 'Nettoyage',
            self::Analyzing => 'Analyse',
            self::Completed => 'Terminé',
            self::Archived => 'Archivé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Importing, self::Cleaning, self::Analyzing => 'text-bg-info',
            self::Completed => 'text-bg-success',
            self::Archived => 'text-bg-dark',
        };
    }
}
