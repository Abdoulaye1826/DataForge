<?php

namespace App\Enums;

enum InsightCategory: string
{
    case Summary = 'summary';
    case KeyPoints = 'key_points';
    case Anomaly = 'anomaly';
    case Trend = 'trend';
    case Opportunity = 'opportunity';
    case Risk = 'risk';
    case Recommendation = 'recommendation';
    case Visualization = 'visualization';
    case Conclusion = 'conclusion';

    public function label(): string
    {
        return match ($this) {
            self::Summary => 'Résumé',
            self::KeyPoints => 'Points importants',
            self::Anomaly => 'Anomalies',
            self::Trend => 'Tendances',
            self::Opportunity => 'Opportunités',
            self::Risk => 'Risques',
            self::Recommendation => 'Recommandations',
            self::Visualization => 'Graphiques suggérés',
            self::Conclusion => 'Conclusion',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Summary => '📋',
            self::KeyPoints => '📌',
            self::Anomaly => '⚠️',
            self::Trend => '📈',
            self::Opportunity => '💡',
            self::Risk => '🚨',
            self::Recommendation => '✅',
            self::Visualization => '📊',
            self::Conclusion => '🏁',
        };
    }

    /** @return array<self> Display order for the insights panel. */
    public static function ordered(): array
    {
        return [
            self::Summary, self::KeyPoints, self::Trend, self::Anomaly,
            self::Opportunity, self::Risk, self::Recommendation,
            self::Visualization, self::Conclusion,
        ];
    }
}
