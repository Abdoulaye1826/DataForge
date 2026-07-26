<?php

namespace App\Enums;

enum PipelineSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Rejected => 'Rejetée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-warning',
            self::Accepted => 'text-bg-success',
            self::Rejected => 'text-bg-secondary',
        };
    }
}
