<?php

namespace App\Services\Export;

use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Python\PythonRunnerService;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Module Export: hands a table's cached data to export_table.py for
 * conversion to whichever format the user picked. Each call gets its own
 * tmp subdirectory so concurrent exports never collide; the controller
 * streams the resulting file back and deletes it once sent.
 */
class TableExportService
{
    private const FORMATS = ['csv', 'xlsx', 'json'];

    public function __construct(private readonly PythonRunnerService $pythonRunner)
    {
    }

    /** @return array{path: string, filename: string} */
    public function export(DatasetTable $table, Project $project, string $format): array
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException("Format d'export non supporté : {$format}");
        }

        $outputDir = config('dataforge.exports.tmp_path') . DIRECTORY_SEPARATOR . (string) Str::uuid();

        $result = $this->pythonRunner->run('export_table.py', [
            'storage_path' => $table->storage_path,
            'format' => $format,
            'output_dir' => $outputDir,
            'file_stem' => $table->name,
        ], $project->id);

        return [
            'path' => $result->data['file_path'],
            'filename' => "{$table->name}.{$format}",
        ];
    }
}
