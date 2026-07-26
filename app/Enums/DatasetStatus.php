<?php

namespace App\Enums;

enum DatasetStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Imported => 'Importé',
            self::Error => 'Erreur',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-secondary',
            self::Imported => 'text-bg-success',
            self::Error => 'text-bg-danger',
        };
    }
}
