<?php

namespace App\Providers;

use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\AiConversationRepositoryInterface;
use App\Repositories\Contracts\AiInsightRepositoryInterface;
use App\Repositories\Contracts\AnalysisRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\DashboardWidgetRepositoryInterface;
use App\Repositories\Contracts\DatabaseConnectionRepositoryInterface;
use App\Repositories\Contracts\DatasetRelationshipRepositoryInterface;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\DatasetTableRepositoryInterface;
use App\Repositories\Contracts\MlAnalysisRepositoryInterface;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use App\Repositories\Contracts\PipelineSuggestionRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\QualityReportRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Contracts\StatisticalTestRepositoryInterface;
use App\Repositories\Contracts\UserPipelinePreferenceRepositoryInterface;
use App\Repositories\Contracts\VisualizationRepositoryInterface;
use App\Repositories\Contracts\VisualizationSuggestionRepositoryInterface;
use App\Repositories\Eloquent\EloquentActivityLogRepository;
use App\Repositories\Eloquent\EloquentAiConversationRepository;
use App\Repositories\Eloquent\EloquentAiInsightRepository;
use App\Repositories\Eloquent\EloquentAnalysisRepository;
use App\Repositories\Eloquent\EloquentDashboardRepository;
use App\Repositories\Eloquent\EloquentDashboardWidgetRepository;
use App\Repositories\Eloquent\EloquentDatabaseConnectionRepository;
use App\Repositories\Eloquent\EloquentDatasetRelationshipRepository;
use App\Repositories\Eloquent\EloquentDatasetRepository;
use App\Repositories\Eloquent\EloquentDatasetTableRepository;
use App\Repositories\Eloquent\EloquentMlAnalysisRepository;
use App\Repositories\Eloquent\EloquentPipelineStepRepository;
use App\Repositories\Eloquent\EloquentPipelineSuggestionRepository;
use App\Repositories\Eloquent\EloquentProjectRepository;
use App\Repositories\Eloquent\EloquentQualityReportRepository;
use App\Repositories\Eloquent\EloquentReportRepository;
use App\Repositories\Eloquent\EloquentStatisticalTestRepository;
use App\Repositories\Eloquent\EloquentUserPipelinePreferenceRepository;
use App\Repositories\Eloquent\EloquentVisualizationRepository;
use App\Repositories\Eloquent\EloquentVisualizationSuggestionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
        $this->app->bind(DatasetRepositoryInterface::class, EloquentDatasetRepository::class);
        $this->app->bind(DatasetTableRepositoryInterface::class, EloquentDatasetTableRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, EloquentActivityLogRepository::class);
        $this->app->bind(QualityReportRepositoryInterface::class, EloquentQualityReportRepository::class);
        $this->app->bind(DatasetRelationshipRepositoryInterface::class, EloquentDatasetRelationshipRepository::class);
        $this->app->bind(PipelineStepRepositoryInterface::class, EloquentPipelineStepRepository::class);
        $this->app->bind(AnalysisRepositoryInterface::class, EloquentAnalysisRepository::class);
        $this->app->bind(VisualizationRepositoryInterface::class, EloquentVisualizationRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, EloquentDashboardRepository::class);
        $this->app->bind(DashboardWidgetRepositoryInterface::class, EloquentDashboardWidgetRepository::class);
        $this->app->bind(AiConversationRepositoryInterface::class, EloquentAiConversationRepository::class);
        $this->app->bind(AiInsightRepositoryInterface::class, EloquentAiInsightRepository::class);
        $this->app->bind(ReportRepositoryInterface::class, EloquentReportRepository::class);
        $this->app->bind(StatisticalTestRepositoryInterface::class, EloquentStatisticalTestRepository::class);
        $this->app->bind(MlAnalysisRepositoryInterface::class, EloquentMlAnalysisRepository::class);
        $this->app->bind(PipelineSuggestionRepositoryInterface::class, EloquentPipelineSuggestionRepository::class);
        $this->app->bind(UserPipelinePreferenceRepositoryInterface::class, EloquentUserPipelinePreferenceRepository::class);
        $this->app->bind(DatabaseConnectionRepositoryInterface::class, EloquentDatabaseConnectionRepository::class);
        $this->app->bind(VisualizationSuggestionRepositoryInterface::class, EloquentVisualizationSuggestionRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
