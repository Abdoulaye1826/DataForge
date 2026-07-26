<?php

namespace App\Enums;

enum StatisticalTestType: string
{
    case TTest = 't_test';
    case ChiSquare = 'chi_square';
    case Anova = 'anova';
    case Correlation = 'correlation';

    public function label(): string
    {
        return match ($this) {
            self::TTest => 'Test t (comparer 2 groupes)',
            self::ChiSquare => 'Test du χ² (indépendance de 2 variables catégorielles)',
            self::Anova => 'ANOVA (comparer 3+ groupes)',
            self::Correlation => 'Corrélation (significativité)',
        };
    }

    /** @return string[] Config keys the form must submit for this test type. */
    public function requiredFields(): array
    {
        return match ($this) {
            self::TTest => ['numeric_column', 'group_column'],
            self::ChiSquare => ['column_a', 'column_b'],
            self::Anova => ['numeric_column', 'group_column'],
            self::Correlation => ['column_a', 'column_b'],
        };
    }
}
