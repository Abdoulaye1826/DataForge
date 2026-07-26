<?php

namespace App\Enums;

/**
 * Module Contexte métier: the business domain a project belongs to. Fed to
 * every AI prompt (see AiContextBuilder) so insights, quality diagnostics
 * and pipeline suggestions are framed for the right field rather than
 * guessed from column names alone.
 */
enum ProjectDomain: string
{
    case Finance = 'finance';
    case Marketing = 'marketing';
    case Hr = 'hr';
    case Health = 'health';
    case Commerce = 'commerce';
    case Logistics = 'logistics';
    case Industry = 'industry';
    case Research = 'research';
    case Administration = 'administration';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Finance => 'Finance',
            self::Marketing => 'Marketing',
            self::Hr => 'Ressources humaines',
            self::Health => 'Santé',
            self::Commerce => 'Commerce',
            self::Logistics => 'Logistique',
            self::Industry => 'Industrie',
            self::Research => 'Recherche',
            self::Administration => 'Administration',
            self::Other => 'Autre',
        };
    }
}
