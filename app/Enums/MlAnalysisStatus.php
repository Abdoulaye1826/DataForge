<?php

namespace App\Enums;

enum MlAnalysisStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En cours',
            self::Completed => 'Terminée',
            self::Failed => 'Échouée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-warning',
            self::Completed => 'text-bg-success',
            self::Failed => 'text-bg-danger',
        };
    }
}
