<?php

namespace App\Services\Import;

use App\Enums\DatasetFormat;
use App\Enums\DatasetStatus;
use App\Exceptions\PythonExecutionException;
use App\Exceptions\UnsupportedFileFormatException;
use App\Jobs\ProcessDatasetImportJob;
use App\Models\Dataset;
use App\Models\DatabaseConnection;
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
        private readonly DatabaseConnectionService $databaseConnections,
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

        ProcessDatasetImportJob::dispatch($dataset, $project, 'dataset.imported', 'importé');

        return $dataset;
    }

    /**
     * Module "Connecteurs SQL": same Pending-row-then-async-job shape as
     * import(), but the source is a live database table instead of an
     * uploaded file - disk_path/size_bytes stay empty (like a joined/derived
     * dataset) and database_connection_id + original_filename (the remote
     * table's own name) are kept so reimport() can re-fetch fresh data later.
     */
    public function importFromDatabase(Project $project, DatabaseConnection $connection, string $tableName): Dataset
    {
        $dataset = $this->datasets->create([
            'project_id' => $project->id,
            'database_connection_id' => $connection->id,
            'name' => "{$connection->name} · {$tableName}",
            'original_filename' => $tableName,
            'format' => DatasetFormat::Sql,
            'disk_path' => null,
            'size_bytes' => 0,
            'status' => DatasetStatus::Pending,
        ]);

        ProcessDatasetImportJob::dispatch($dataset, $project, 'dataset.imported', 'importé');

        return $dataset;
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
        $dataset->update(['status' => DatasetStatus::Pending]);

        ProcessDatasetImportJob::dispatch($dataset, $project, 'dataset.reimported', 'retraité');

        return $dataset;
    }

    /**
     * The heavy part of an import: real Python parsing + the full onboarding
     * cascade (quality, EDA, AI insights, default charts) for every table
     * produced. Public so ProcessDatasetImportJob can run it off the request
     * thread - the caller is responsible for having already created the
     * Dataset row (status Pending) synchronously.
     */
    public function processDataset(Dataset $dataset, Project $project, string $action, string $verb): void
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

        if ($format === DatasetFormat::Sql) {
            return [[$this->readTableFromDatabase($dataset, $outputDir)], []];
        }

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

    /**
     * @return array{name: string, row_count: int, column_count: int, storage_path: string}
     */
    private function readTableFromDatabase(Dataset $dataset, string $outputDir): array
    {
        $connection = $dataset->databaseConnection;

        if ($connection === null) {
            throw new UnsupportedFileFormatException('La connexion à la base de données source a été supprimée - impossible de retraiter ce dataset.');
        }

        $result = $this->pythonRunner->run('db_import_table.py', [
            'connection' => $this->databaseConnections->connectionPayload($connection),
            // original_filename holds the remote table's own name (see
            // importFromDatabase()), not an uploaded file name.
            'table_name' => $dataset->original_filename,
            'output_dir' => $outputDir,
        ], $dataset->project_id);

        return $result->data;
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
