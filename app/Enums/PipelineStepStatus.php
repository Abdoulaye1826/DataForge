<?php

namespace App\Enums;

enum PipelineStepStatus: string
{
    case Applied = 'applied';
    case Reverted = 'reverted';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Appliquée',
            self::Reverted => 'Annulée',
            self::Failed => 'Échouée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Applied => 'text-bg-success',
            self::Reverted => 'text-bg-secondary',
            self::Failed => 'text-bg-danger',
        };
    }
}
