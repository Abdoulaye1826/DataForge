<?php

namespace App\Enums;

enum PipelineStepStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Reverted = 'reverted';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En cours',
            self::Applied => 'Appliquée',
            self::Reverted => 'Annulée',
            self::Failed => 'Échouée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-warning',
            self::Applied => 'text-bg-success',
            self::Reverted => 'text-bg-secondary',
            self::Failed => 'text-bg-danger',
        };
    }
}
