<?php

namespace App\Enums;

enum ColumnType: string
{
    case Integer = 'integer';
    case Float = 'float';
    case String = 'string';
    case Date = 'date';
    case Datetime = 'datetime';
    case Boolean = 'boolean';
    case Category = 'category';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Integer => 'Entier',
            self::Float => 'Décimal',
            self::String => 'Texte',
            self::Date => 'Date',
            self::Datetime => 'Date et heure',
            self::Boolean => 'Booléen',
            self::Category => 'Catégorie',
            self::Unknown => 'Inconnu',
        };
    }

    public function isNumeric(): bool
    {
        return $this === self::Integer || $this === self::Float;
    }
}
