<?php

namespace App\Support\Schema;

use Illuminate\Support\Facades\DB;

/**
 * Portable CHECK constraints (Part 4.0): ANSI `ALTER TABLE … ADD CONSTRAINT
 * … CHECK (…)` on MySQL 8.0.16+, MariaDB and PostgreSQL. SQLite cannot
 * add constraints after creation, so they are skipped there (tests that
 * assert CHECKs run on MySQL/PostgreSQL in CI).
 */
final class Checks
{
    public static function supported(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }

    public static function add(string $table, string $name, string $expression): void
    {
        if (! self::supported()) {
            return;
        }
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }

    /** CHECK col IN ('a','b') */
    public static function in(string $table, string $column, array $values, bool $nullable = false): void
    {
        $list = "'".implode("','", $values)."'";
        $expr = "{$column} IN ({$list})";
        if ($nullable) {
            $expr = "{$column} IS NULL OR {$expr}";
        }
        self::add($table, "{$table}_{$column}_check", $expr);
    }
}
