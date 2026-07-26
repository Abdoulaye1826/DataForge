<?php

namespace App\Enums;

/**
 * Module Contexte métier: what the user is actually trying to accomplish
 * with a project. Combined with ProjectDomain, this is the first thing
 * AiContextBuilder puts in front of the model - "Domaine : Santé. Objectif :
 * détecter les anomalies" changes what a generic insight generator produces
 * far more than another column of raw stats would.
 */
enum ProjectObjective: string
{
    case Dashboard = 'dashboard';
    case UnderstandSales = 'understand_sales';
    case DetectAnomalies = 'detect_anomalies';
    case Forecast = 'forecast';
    case SegmentCustomers = 'segment_customers';
    case CleanData = 'clean_data';
    case BuildReport = 'build_report';
    case Explore = 'explore';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Créer un tableau de bord',
            self::UnderstandSales => 'Comprendre les ventes',
            self::DetectAnomalies => 'Détecter les anomalies',
            self::Forecast => 'Prévoir une tendance',
            self::SegmentCustomers => 'Segmenter les clients',
            self::CleanData => 'Nettoyer les données',
            self::BuildReport => 'Construire un rapport',
            self::Explore => 'Explorer les données',
            self::Other => 'Autre',
        };
    }
}
