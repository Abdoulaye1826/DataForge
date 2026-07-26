<?php

namespace App\Services\Import;

use App\Enums\DatasetFormat;
use App\Enums\DatasetStatus;
use App\Exceptions\PythonExecutionException;
use App\Exceptions\UnsupportedFileFormatException;
use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\DatasetTableRepositoryInterface;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use App\Services\Activity\ActivityLogService;
use App\Services\Pipeline\TableOnboardingService;
use App\Services\Python\PythonRunnerService;
use App\Services\Quality\RelationshipDetectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Module 3 (Importation) + Module "Compréhension automatique": stores the
 * uploaded file, hands it to import_dataset.py to turn it into one or more
 * normalized table caches (one per Excel sheet, for instance), then runs
 * analyze_structure.py on each table to populate its columns. Everything
 * read-only/informational in the pipeline runs automatically right after -
 * quality audit, exploratory analysis, AI insights, a handful of default
 * charts - so a table is fully understood with zero clicks by the time the
 * user opens it. Nettoyage/Prétraitement stay manual on purpose: those
 * mutate the data, so the spec (and the user) wants a human decision there,
 * not automation.
 */
class DatasetImportService
{
    public function __construct(
        private readonly DatasetRepositoryInterface $datasets,
        private readonly DatasetTableRepositoryInterface $datasetTables,
        private readonly PythonRunnerService $pythonRunner,
        private readonly ActivityLogService $activityLogService,
        private readonly RelationshipDetectionService $relationshipDetection,
        private readonly PipelineStepRepositoryInterface $pipelineSteps,
        private readonly TableOnboardingService $onboarding,
    ) {
    }

    public function import(Project $project, UploadedFile $file): Dataset
    {
        $format = DatasetFormat::fromExtension($file->getClientOriginalExtension());

        if ($format === null) {
            throw new UnsupportedFileFormatException(
                "Format de fichier non supporté : .{$file->getClientOriginalExtension()}"
            );
        }

        if ($format === DatasetFormat::Sql) {
            throw new UnsupportedFileFormatException('L\'import depuis une source SQL n\'est pas encore disponible.');
        }

        $diskPath = $file->store("datasets/{$project->id}/raw");

        $dataset = $this->datasets->create([
            'project_id' => $project->id,
            'name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'format' => $format,
            'disk_path' => $diskPath,
            'size_bytes' => $file->getSize(),
            'status' => DatasetStatus::Pending,
        ]);

        $this->processDataset($dataset, $project, 'dataset.imported', 'importé');

        return $dataset->fresh()->load('tables.columns');
    }

    /**
     * Re-reads a dataset's original file from disk and rebuilds its tables
     * from scratch (dropping the existing ones first) - useful whenever a
     * pipeline improvement (e.g. delimiter auto-detection) means an older
     * import no longer reflects what the file actually contains, without
     * requiring the user to re-upload it.
     */
    public function reimport(Dataset $dataset, Project $project): Dataset
    {
        if ($dataset->format === DatasetFormat::Derived) {
            throw new UnsupportedFileFormatException('Une table jointe ne peut pas être retraitée depuis un fichier - recréez la jointure depuis la page Relations.');
        }

        $dataset->tables()->get()->each->delete();

        $this->processDataset($dataset, $project, 'dataset.reimported', 'retraité');

        return $dataset->fresh()->load('tables.columns');
    }

    private function processDataset(Dataset $dataset, Project $project, string $action, string $verb): void
    {
        try {
            [$tables, $skippedSheets] = $this->readTables($dataset, $dataset->format);

            foreach ($tables as $tableData) {
                $datasetTable = $this->datasetTables->create([
                    'dataset_id' => $dataset->id,
                    'name' => $tableData['name'],
                    'row_count' => $tableData['row_count'],
                    'column_count' => $tableData['column_count'],
                    'storage_path' => $tableData['storage_path'],
                    'is_primary' => count($tables) === 1,
                ]);

                // Colonnes, audit qualité, analyse exploratoire (+ insights
                // IA en cascade) et quelques visualisations par défaut :
                // tout ce qui est lecture seule se fait sans action de
                // l'utilisateur.
                $this->onboarding->onboard($datasetTable, $project);
                $this->logImportStep($datasetTable, $project);
            }

            $this->datasets->update($dataset, [
                'status' => DatasetStatus::Imported,
                'import_meta' => ['tables_count' => count($tables), 'skipped_sheets' => $skippedSheets],
            ]);

            $this->relationshipDetection->detectForProject($project);

            $message = "Dataset « {$dataset->name} » {$verb} (" . count($tables) . ' table(s)).';
            if ($skippedSheets !== []) {
                $message .= ' Feuille(s) ignorée(s) car vide(s) : ' . implode(', ', $skippedSheets) . '.';
            }

            $this->activityLogService->log($project, $action, $message, $dataset);
        } catch (PythonExecutionException $e) {
            $this->datasets->update($dataset, [
                'status' => DatasetStatus::Error,
                'import_meta' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    /**
     * @return array{0: array<int, array{name: string, row_count: int, column_count: int, storage_path: string}>, 1: array<int, string>}
     */
    private function readTables(Dataset $dataset, DatasetFormat $format): array
    {
        $outputDir = storage_path("app/datasets/{$dataset->id}/tables");

        $result = $this->pythonRunner->run('import_dataset.py', [
            'source_path' => Storage::path($dataset->disk_path),
            'format' => $format->value,
            'output_dir' => $outputDir,
            // disk_path is a randomly hashed storage filename - pass the
            // original name explicitly so single-table formats (csv/json/
            // parquet) get a human-readable table name instead of the hash.
            'default_name' => pathinfo($dataset->original_filename, PATHINFO_FILENAME),
        ], $dataset->project_id);

        return [$result->data['tables'], $result->data['skipped_sheets'] ?? []];
    }

    private function logImportStep(DatasetTable $table, Project $project): void
    {
        $this->pipelineSteps->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'step_order' => $this->pipelineSteps->nextStepOrder($project->id),
            'step_type' => 'import',
            'label' => "Importation de « {$table->name} » ({$table->row_count} lignes, {$table->column_count} colonnes)",
            'params' => ['row_count' => $table->row_count, 'column_count' => $table->column_count],
            'status' => 'applied',
            'rows_affected' => $table->row_count,
            'applied_at' => now(),
        ]);
    }
}
