<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardBuilderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardWidgetController;
use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\DatasetImportController;
use App\Http\Controllers\DatasetsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MlAnalysisController;
use App\Http\Controllers\NotebookController;
use App\Http\Controllers\PipelinesController;
use App\Http\Controllers\PipelineStepController;
use App\Http\Controllers\PipelineSuggestionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\RelationshipController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatisticalTestController;
use App\Http\Controllers\TableDataController;
use App\Http\Controllers\VisualizationController;
use App\Http\Controllers\VisualizationSuggestionController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('workspace');
});

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::get('/home', [WorkspaceController::class, 'index'])->name('workspace');
    Route::get('/analytics', [DashboardController::class, 'index'])->name('analytics');
    Route::get('/datasets', [DatasetsController::class, 'index'])->name('datasets.index');
    Route::get('/pipelines', [PipelinesController::class, 'index'])->name('pipelines.index');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');

    Route::resource('projects', ProjectController::class)->except(['create', 'edit']);

    Route::prefix('projects/{project}')->name('projects.')->scopeBindings()->group(function () {
        Route::get('notebook', [NotebookController::class, 'show'])->name('notebook.show');
        Route::post('notebook/replay', [NotebookController::class, 'replay'])->name('notebook.replay');

        Route::get('assistant', [AiAssistantController::class, 'index'])->name('assistant.index');
        Route::post('assistant/conversations', [AiAssistantController::class, 'storeConversation'])->name('assistant.conversations.store');
        Route::post('assistant/conversations/{aiConversation}/messages', [AiAssistantController::class, 'storeMessage'])->name('assistant.messages.store')->middleware('throttle:ai');

        Route::get('dashboards', [DashboardBuilderController::class, 'index'])->name('dashboards.index');
        Route::post('dashboards', [DashboardBuilderController::class, 'store'])->name('dashboards.store');
        Route::get('dashboards/{dashboard}', [DashboardBuilderController::class, 'show'])->name('dashboards.show');
        Route::post('dashboards/{dashboard}/duplicate', [DashboardBuilderController::class, 'duplicate'])->name('dashboards.duplicate');
        Route::delete('dashboards/{dashboard}', [DashboardBuilderController::class, 'destroy'])->name('dashboards.destroy');

        Route::prefix('dashboards/{dashboard}')->name('dashboards.')->group(function () {
            Route::post('widgets', [DashboardWidgetController::class, 'store'])->name('widgets.store');
            Route::put('widgets/{widget}', [DashboardWidgetController::class, 'update'])->name('widgets.update');
            Route::delete('widgets/{widget}', [DashboardWidgetController::class, 'destroy'])->name('widgets.destroy');
            Route::get('widgets/{widget}/data', [DashboardWidgetController::class, 'data'])->name('widgets.data')->middleware('throttle:read');
        });

        Route::post('datasets/import', [DatasetImportController::class, 'store'])->name('datasets.import')->middleware('throttle:heavy');
        Route::post('datasets/import-demo', [DatasetImportController::class, 'importDemo'])->name('datasets.import-demo')->middleware('throttle:heavy');

        Route::post('database-connections', [DatabaseConnectionController::class, 'store'])->name('database-connections.store')->middleware('throttle:heavy');
        Route::delete('database-connections/{connection}', [DatabaseConnectionController::class, 'destroy'])->name('database-connections.destroy');
        Route::get('database-connections/{connection}/tables', [DatabaseConnectionController::class, 'tables'])->name('database-connections.tables')->middleware('throttle:heavy');
        Route::post('database-connections/{connection}/import', [DatabaseConnectionController::class, 'import'])->name('database-connections.import')->middleware('throttle:heavy');
        Route::get('datasets/{dataset}', [DatasetController::class, 'show'])->name('datasets.show');
        Route::get('datasets/{dataset}/intelligence', [DatasetController::class, 'intelligence'])->name('datasets.intelligence');
        Route::post('datasets/{dataset}/reimport', [DatasetController::class, 'reimport'])->name('datasets.reimport')->middleware('throttle:heavy');
        Route::delete('datasets/{dataset}', [DatasetController::class, 'destroy'])->name('datasets.destroy');

        Route::get('relationships', [RelationshipController::class, 'index'])->name('relationships.index');
        Route::post('relationships/{relationship}/confirm', [RelationshipController::class, 'confirm'])->name('relationships.confirm');
        Route::post('relationships/{relationship}/reject', [RelationshipController::class, 'reject'])->name('relationships.reject');
        Route::post('relationships/{relationship}/join', [RelationshipController::class, 'join'])->name('relationships.join')->middleware('throttle:heavy');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports', [ReportController::class, 'store'])->name('reports.store')->middleware('throttle:ai');
        Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
        Route::delete('reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

        Route::post('visualization-suggestions', [VisualizationSuggestionController::class, 'store'])->name('visualization-suggestions.store')->middleware('throttle:ai');
        Route::post('visualization-suggestions/{visualizationSuggestion}/accept', [VisualizationSuggestionController::class, 'accept'])->name('visualization-suggestions.accept')->middleware('throttle:heavy');
        Route::post('visualization-suggestions/{visualizationSuggestion}/reject', [VisualizationSuggestionController::class, 'reject'])->name('visualization-suggestions.reject');

        Route::post('dashboards/{dashboard}/export', [DashboardBuilderController::class, 'export'])->name('dashboards.export')->middleware('throttle:heavy');

        Route::prefix('datasets/{dataset}')->name('datasets.')->group(function () {
            Route::get('tables/{table}/quality', [QualityController::class, 'show'])->name('tables.quality.show');
            Route::post('tables/{table}/quality/refresh', [QualityController::class, 'refresh'])->name('tables.quality.refresh')->middleware('throttle:ai');
            Route::post('tables/{table}/pipeline-steps', [PipelineStepController::class, 'store'])->name('tables.pipeline-steps.store')->middleware('throttle:heavy');

            Route::post('tables/{table}/pipeline-suggestions', [PipelineSuggestionController::class, 'store'])->name('tables.pipeline-suggestions.store')->middleware('throttle:ai');
            Route::post('tables/{table}/pipeline-suggestions/accept-all', [PipelineSuggestionController::class, 'acceptAll'])->name('tables.pipeline-suggestions.accept-all')->middleware('throttle:heavy');
            Route::post('tables/{table}/pipeline-suggestions/{pipelineSuggestion}/accept', [PipelineSuggestionController::class, 'accept'])->name('tables.pipeline-suggestions.accept')->middleware('throttle:heavy');
            Route::post('tables/{table}/pipeline-suggestions/{pipelineSuggestion}/reject', [PipelineSuggestionController::class, 'reject'])->name('tables.pipeline-suggestions.reject');

            Route::get('tables/{table}/analysis', [AnalysisController::class, 'show'])->name('tables.analysis.show');
            Route::post('tables/{table}/analysis/run', [AnalysisController::class, 'run'])->name('tables.analysis.run')->middleware('throttle:ai');

            Route::post('tables/{table}/statistical-tests', [StatisticalTestController::class, 'store'])->name('tables.statistical-tests.store')->middleware('throttle:heavy');
            Route::delete('tables/{table}/statistical-tests/{statisticalTest}', [StatisticalTestController::class, 'destroy'])->name('tables.statistical-tests.destroy');

            Route::get('tables/{table}/ml', [MlAnalysisController::class, 'show'])->name('tables.ml.show');
            Route::post('tables/{table}/ml', [MlAnalysisController::class, 'store'])->name('tables.ml.store')->middleware('throttle:heavy');
            Route::delete('tables/{table}/ml/{mlAnalysis}', [MlAnalysisController::class, 'destroy'])->name('tables.ml.destroy');

            Route::get('tables/{table}/export', [ExportController::class, 'table'])->name('tables.export')->middleware('throttle:heavy');

            Route::get('tables/{table}/data', [TableDataController::class, 'show'])->name('tables.data.show');
            Route::get('tables/{table}/data/rows', [TableDataController::class, 'rows'])->name('tables.data.rows')->middleware('throttle:read');

            Route::get('tables/{table}/visualizations', [VisualizationController::class, 'index'])->name('tables.visualizations.index');
            Route::post('tables/{table}/visualizations', [VisualizationController::class, 'store'])->name('tables.visualizations.store')->middleware('throttle:heavy');
            Route::post('tables/{table}/visualizations/{visualization}/refresh', [VisualizationController::class, 'refresh'])->name('tables.visualizations.refresh')->middleware('throttle:heavy');
            Route::delete('tables/{table}/visualizations/{visualization}', [VisualizationController::class, 'destroy'])->name('tables.visualizations.destroy');
        });
    });
});
