<?php

namespace App\Services\Dashboard;

use App\Enums\GeneratedBy;
use App\Enums\WidgetType;
use App\Models\Dashboard;
use App\Models\Project;
use App\Models\Report;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Python\PythonRunnerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Module Export dashboard : exporte un dashboard BI (widgets graphique/KPI/
 * tableau/texte) en PDF - réutilise exactement le même moteur que le Rapport
 * narratif (§10) : render_chart_image.py pour rasteriser les graphiques
 * (dompdf n'a pas de moteur JS, les canvas Chart.js/ApexCharts affichés à
 * l'écran ne sont pas réutilisables) et le modèle Report existant pour le
 * stockage/téléchargement, plutôt que de dupliquer cette mécanique.
 */
class DashboardExportService
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly ReportRepositoryInterface $reports,
        private readonly PythonRunnerService $pythonRunner,
    ) {
    }

    public function generate(Dashboard $dashboard, Project $project): Report
    {
        $dashboard->loadMissing('widgets.visualization');

        $rendered = $dashboard->widgets->map(fn ($widget) => [
            'widget' => $widget,
            'data' => $this->dashboardService->renderData($widget),
        ]);

        $chartImages = $this->renderChartImages($rendered, $project->id);

        $html = view('dashboards.pdf', [
            'project' => $project,
            'dashboard' => $dashboard,
            'widgets' => $rendered,
            'chartImages' => $chartImages,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $binary = $pdf->output();

        $directory = config('dataforge.reports.storage_path') . DIRECTORY_SEPARATOR . $project->id;
        File::ensureDirectoryExists($directory);

        $filename = Str::slug($dashboard->name) . '-' . now()->format('Y-m-d-His') . '-' . Str::random(6) . '.pdf';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        File::put($path, $binary);

        return $this->reports->create([
            'project_id' => $project->id,
            'title' => "Dashboard · {$dashboard->name} · " . now()->format('d/m/Y'),
            'sections' => ['dashboard'],
            'storage_path' => $path,
            'size_bytes' => File::size($path),
            'generated_by' => GeneratedBy::OnDemand,
        ]);
    }

    /**
     * @return array<int, string> widget id => base64 PNG
     */
    private function renderChartImages(Collection $rendered, int $projectId): array
    {
        $chartEntries = $rendered
            ->filter(fn ($entry) => $entry['widget']->widget_type === WidgetType::Chart && ! empty($entry['data']['chart_type']))
            ->values();

        if ($chartEntries->isEmpty()) {
            return [];
        }

        $result = $this->pythonRunner->run('render_chart_image.py', [
            'charts' => $chartEntries->map(fn ($entry) => [
                'chart_type' => $entry['data']['chart_type'],
                'name' => $entry['widget']->title,
                'data' => $entry['data']['data'],
            ])->all(),
        ], $projectId);

        $images = collect($result->data['images']);

        return $chartEntries
            ->mapWithKeys(fn ($entry, $index) => [$entry['widget']->id => $images->get($index)['base64'] ?? null])
            ->filter()
            ->all();
    }
}
