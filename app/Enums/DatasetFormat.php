<?php

namespace App\Enums;

enum DatasetFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Json = 'json';
    case Parquet = 'parquet';
    case Sql = 'sql';
    case Derived = 'derived';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Xlsx => 'Excel',
            self::Json => 'JSON',
            self::Parquet => 'Parquet',
            self::Sql => 'SQL',
            self::Derived => 'Jointure',
        };
    }

    /** Maps an uploaded file's extension to a supported format, or null if unsupported. */
    public static function fromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'csv' => self::Csv,
            'xlsx', 'xls' => self::Xlsx,
            'json' => self::Json,
            'parquet' => self::Parquet,
            'sql' => self::Sql,
            default => null,
        };
    }
}
