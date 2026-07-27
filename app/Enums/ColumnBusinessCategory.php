<?php

namespace App\Enums;

/**
 * Module Compréhension: business-meaning classification layered on top of
 * ColumnType (which stays purely technical/statistical - used for parsing,
 * conversion, chart rendering). This is what the AI infers alongside the
 * semantic label, e.g. "amount" is technically a Float but its business
 * category is Monetary.
 */
enum ColumnBusinessCategory: string
{
    case Numeric = 'numeric';
    case Categorical = 'categorical';
    case Temporal = 'temporal';
    case Text = 'text';
    case Boolean = 'boolean';
    case Geographic = 'geographic';
    case Identifier = 'identifier';
    case Calculated = 'calculated';
    case Monetary = 'monetary';
    case Sensitive = 'sensitive';

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Numérique',
            self::Categorical => 'Catégorielle',
            self::Temporal => 'Temporelle',
            self::Text => 'Texte',
            self::Boolean => 'Booléenne',
            self::Geographic => 'Géographique',
            self::Identifier => 'Identifiant',
            self::Calculated => 'Calculée',
            self::Monetary => 'Monétaire',
            self::Sensitive => 'Sensible',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Identifier => 'text-bg-secondary',
            self::Monetary => 'text-bg-success',
            self::Temporal => 'text-bg-info',
            self::Sensitive => 'text-bg-danger',
            self::Geographic => 'text-bg-warning',
            default => 'text-bg-light border',
        };
    }
}
