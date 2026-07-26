<?php

namespace App\Enums;

enum PipelineStepType: string
{
    case Import = 'import';

    // Preprocessing (Module Prétraitement)
    case RenameColumn = 'rename_column';
    case DropColumn = 'drop_column';
    case Merge = 'merge';
    case Split = 'split';
    case Filter = 'filter';
    case CreateColumn = 'create_column';
    case ConvertType = 'convert_type';
    case Encode = 'encode';
    case Normalize = 'normalize';
    case Standardize = 'standardize';
    case Categorize = 'categorize';

    // Cleaning (Module Nettoyage)
    case Dedupe = 'dedupe';
    case FixDates = 'fix_dates';
    case TrimSpaces = 'trim_spaces';
    case FixCase = 'fix_case';

    // Jointure (Module Gestion des relations)
    case Join = 'join';

    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Importation',
            self::RenameColumn => 'Renommer une colonne',
            self::DropColumn => 'Supprimer une colonne',
            self::Merge => 'Fusionner des colonnes',
            self::Split => 'Séparer une colonne',
            self::Filter => 'Filtrer les lignes',
            self::CreateColumn => 'Créer une colonne calculée',
            self::ConvertType => 'Convertir un type',
            self::Encode => 'Encoder une variable',
            self::Normalize => 'Normaliser',
            self::Standardize => 'Standardiser',
            self::Categorize => 'Créer des catégories',
            self::Dedupe => 'Supprimer les doublons',
            self::FixDates => 'Corriger les dates',
            self::TrimSpaces => 'Supprimer les espaces inutiles',
            self::FixCase => 'Corriger la casse',
            self::Join => 'Jointure de tables',
            self::Custom => 'Opération personnalisée',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Dedupe, self::FixDates, self::TrimSpaces, self::FixCase => 'cleaning',
            self::Import => 'import',
            self::Join => 'join',
            default => 'preprocessing',
        };
    }

    /** @return array<self> Cleaning-category operations, for building the "Nettoyage" form group. */
    public static function cleaningTypes(): array
    {
        return [self::Dedupe, self::TrimSpaces, self::FixCase, self::FixDates];
    }

    /** @return array<self> Preprocessing-category operations, for the "Prétraitement" form group. */
    public static function preprocessingTypes(): array
    {
        return [
            self::RenameColumn, self::DropColumn, self::Merge, self::Split, self::Filter,
            self::CreateColumn, self::ConvertType, self::Encode, self::Normalize,
            self::Standardize, self::Categorize,
        ];
    }
}
