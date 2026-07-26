<?php

namespace App\Enums;

enum DatabaseDriver: string
{
    case Pgsql = 'pgsql';
    case Mysql = 'mysql';

    public function label(): string
    {
        return match ($this) {
            self::Pgsql => 'PostgreSQL',
            self::Mysql => 'MySQL',
        };
    }

    public function defaultPort(): int
    {
        return match ($this) {
            self::Pgsql => 5432,
            self::Mysql => 3306,
        };
    }
}
