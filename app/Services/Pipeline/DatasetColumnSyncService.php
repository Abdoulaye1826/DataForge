<?php

namespace App\Services\Pipeline;

use App\Models\DatasetColumn;
use App\Models\DatasetTable;

/**
 * Persists a fresh set of column-analysis results (as returned by
 * analyze_structure.py, clean_data.py or preprocess.py - they all share the
 * same per-column shape) against a table. Used both right after import and
 * after every pipeline step, since a transformation can rename/add/remove
 * columns and change their type - simplest correct approach is to replace
 * the whole set rather than diff it.
 */
class DatasetColumnSyncService
{
    public function sync(DatasetTable $table, array $columns): void
    {
        $table->columns()->delete();

        foreach ($columns as $position => $column) {
            DatasetColumn::create([
                'dataset_table_id' => $table->id,
                'name' => $column['name'],
                'original_name' => $column['name'],
                'position' => $position,
                'detected_type' => $column['detected_type'],
                'current_type' => $column['detected_type'],
                'is_useless' => $column['is_useless'] ?? false,
                'null_count' => $column['null_count'],
                'null_percentage' => $column['null_percentage'],
                'distinct_count' => $column['distinct_count'],
                'sample_values' => $column['sample_values'],
                'stats' => $column['stats'],
            ]);
        }
    }
}
