<?php

namespace App\Enums;

enum InsightImportance: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Medium => 'Moyenne',
            self::High => 'Haute',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'text-bg-light border',
            self::Medium => 'text-bg-info',
            self::High => 'text-bg-danger',
        };
    }
}
