<?php

namespace App\Enums;

enum QualityGrade: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case Average = 'average';
    case Poor = 'poor';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 90 => self::Excellent,
            $score >= 75 => self::Good,
            $score >= 50 => self::Average,
            default => self::Poor,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'Excellent',
            self::Good => 'Bon',
            self::Average => 'Moyen',
            self::Poor => 'Faible',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Excellent => 'text-bg-success',
            self::Good => 'text-bg-info',
            self::Average => 'text-bg-warning',
            self::Poor => 'text-bg-danger',
        };
    }
}
