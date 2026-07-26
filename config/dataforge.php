<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Python bridge
    |--------------------------------------------------------------------------
    |
    | Configuration for the Laravel <-> Python communication used throughout
    | DataForge's data pipeline (import, cleaning, EDA, exports...). Scripts
    | live in the python/ directory at the project root and are invoked via
    | Symfony Process by App\Services\Python\PythonRunnerService.
    |
    */

    'python' => [
        'bin' => env('PYTHON_BIN', 'python'),
        'scripts_path' => base_path('python/scripts'),
        'tmp_path' => storage_path('app/tmp/python'),
        'timeout' => env('PYTHON_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI assistant
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'provider' => env('AI_PROVIDER', 'claude'),
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        ],
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports & exports
    |--------------------------------------------------------------------------
    */

    'reports' => [
        'storage_path' => storage_path('app/reports'),
    ],

    'exports' => [
        'tmp_path' => storage_path('app/tmp/exports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jeux de données de démo
    |--------------------------------------------------------------------------
    |
    | Fichiers bundlés avec l'application (pas des données utilisateur) pour
    | permettre d'essayer DataForge sans avoir à importer son propre fichier -
    | voir DatasetImportController::importDemo(), qui les fait passer par le
    | même DatasetImportService::import() qu'un vrai upload.
    |
    */

    'demo_datasets' => [
        'retail_sales' => [
            'label' => 'Ventes retail',
            'description' => 'Ventes multi-canal (boutique/en ligne) par région et catégorie de produit — 3 mois.',
            'file' => storage_path('app/demo-datasets/ventes_retail.csv'),
        ],
        'telco_churn' => [
            'label' => 'Churn télécom',
            'description' => 'Clients d\'un opérateur télécom avec ancienneté, forfait et statut de résiliation.',
            'file' => storage_path('app/demo-datasets/churn_telecom.csv'),
        ],
        'house_prices' => [
            'label' => 'Prix immobilier',
            'description' => 'Biens immobiliers avec surface, ville et prix de vente.',
            'file' => storage_path('app/demo-datasets/prix_immobilier.csv'),
        ],
    ],

];
