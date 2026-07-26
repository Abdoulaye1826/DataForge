<?php

namespace App\Enums;

enum RelationshipType: string
{
    case OneToOne = 'one_to_one';
    case OneToMany = 'one_to_many';
    case ManyToMany = 'many_to_many';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::OneToOne => '1 : 1',
            self::OneToMany => '1 : N',
            self::ManyToMany => 'N : N',
            self::Unknown => 'Indéterminée',
        };
    }
}
