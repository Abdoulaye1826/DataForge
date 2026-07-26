<?php

namespace App\Enums;

enum RelationshipStatus: string
{
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Suggested => 'Suggérée',
            self::Confirmed => 'Confirmée',
            self::Rejected => 'Rejetée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Suggested => 'text-bg-warning',
            self::Confirmed => 'text-bg-success',
            self::Rejected => 'text-bg-secondary',
        };
    }
}
