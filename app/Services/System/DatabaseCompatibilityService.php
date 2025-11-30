<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Refactor reminder:
 * Relocate to App\\Support\\Database\\Compatibility (or a trait) so it doesn’t inflate the “service” layer.
 * 
 * DatabaseCompatibilityService
 * 
 * Provides database-agnostic SQL functions that work across SQLite, MySQL, and PostgreSQL.
 * This service abstracts vendor-specific SQL syntax into reusable methods.
 * 
 * Supported drivers:
 * - sqlite: SQLite 3
 * - mysql: MySQL 5.7+, MySQL 8.0+
 * - mariadb: MariaDB 10+
 * - pgsql: PostgreSQL 10+
 */
class DatabaseCompatibilityService
{
    /**
     * Get the current database driver
     */
    public static function getDriver(): string
    {
        return config('database.default');
    }

    /**
     * Check if using a specific driver
     */
    public static function isDriver(string $driver): bool
    {
        return self::getDriver() === $driver;
    }

    /**
     * Extract day of week from a timestamp column
     * Returns: 0-6 (Sunday=0, Saturday=6) - consistent across all databases
     * 
     * Usage: DB::raw(DatabaseCompatibilityService::extractDayOfWeek('created_at'))
     */
    public static function extractDayOfWeek(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%w', $column) AS INTEGER)",
            'pgsql' => "EXTRACT(DOW FROM $column)::INTEGER",
            'mysql', 'mariadb' => "MOD(DAYOFWEEK($column) - 1, 7)", // Normalize to 0-6
            default => "EXTRACT(DOW FROM $column)::INTEGER",
        };
    }

    /**
     * Extract hour from a timestamp column
     * Returns: 0-23 (as INTEGER for consistency)
     * 
     * Usage: DB::raw(DatabaseCompatibilityService::extractHour('created_at'))
     */
    public static function extractHour(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%H', $column) AS INTEGER)",
            'pgsql' => "EXTRACT(HOUR FROM $column)::INTEGER",
            'mysql', 'mariadb' => "EXTRACT(HOUR FROM $column)",
            default => "EXTRACT(HOUR FROM $column)::INTEGER",
        };
    }

    /**
     * Extract day of month from a timestamp column
     * Returns: 1-31 (as INTEGER)
     */
    public static function extractDay(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%d', $column) AS INTEGER)",
            'pgsql' => "EXTRACT(DAY FROM $column)::INTEGER",
            'mysql', 'mariadb' => "EXTRACT(DAY FROM $column)",
            default => "EXTRACT(DAY FROM $column)::INTEGER",
        };
    }

    /**
     * Extract month from a timestamp column
     * Returns: 1-12 (as INTEGER)
     */
    public static function extractMonth(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%m', $column) AS INTEGER)",
            'pgsql' => "EXTRACT(MONTH FROM $column)::INTEGER",
            'mysql', 'mariadb' => "EXTRACT(MONTH FROM $column)",
            default => "EXTRACT(MONTH FROM $column)::INTEGER",
        };
    }

    /**
     * Extract year from a timestamp column
     * Returns: YYYY (as INTEGER)
     */
    public static function extractYear(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%Y', $column) AS INTEGER)",
            'pgsql' => "EXTRACT(YEAR FROM $column)::INTEGER",
            'mysql', 'mariadb' => "EXTRACT(YEAR FROM $column)",
            default => "EXTRACT(YEAR FROM $column)::INTEGER",
        };
    }

    /**
     * Extract week number from a timestamp column
     * Returns: 1-53 (as INTEGER)
     */
    public static function extractWeek(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%W', $column) AS INTEGER)",
            'pgsql' => "EXTRACT(WEEK FROM $column)::INTEGER",
            'mysql', 'mariadb' => "WEEK($column)",
            default => "EXTRACT(WEEK FROM $column)::INTEGER",
        };
    }

    /**
     * Extract date portion from a timestamp column
     * Returns: DATE type consistent across all databases
     */
    public static function extractDate(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "DATE($column)",
            'pgsql' => "DATE($column)",
            'mysql', 'mariadb' => "DATE($column)",
            default => "DATE($column)",
        };
    }

    /**
     * Format date for display (YYYY-MM-DD format)
     * Returns: VARCHAR/TEXT formatted as YYYY-MM-DD
     */
    public static function formatDate(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', $column)",
            'pgsql' => "TO_CHAR($column, 'YYYY-MM-DD')",
            'mysql', 'mariadb' => "DATE_FORMAT($column, '%Y-%m-%d')",
            default => "TO_CHAR($column, 'YYYY-MM-DD')",
        };
    }

    /**
     * Format datetime for display (YYYY-MM-DD HH:MM:SS format)
     * Returns: VARCHAR/TEXT formatted as YYYY-MM-DD HH:MM:SS
     */
    public static function formatDateTime(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:%M:%S', $column)",
            'pgsql' => "TO_CHAR($column, 'YYYY-MM-DD HH24:MI:SS')",
            'mysql', 'mariadb' => "DATE_FORMAT($column, '%Y-%m-%d %H:%i:%S')",
            default => "TO_CHAR($column, 'YYYY-MM-DD HH24:MI:SS')",
        };
    }

    /**
     * Calculate difference between two dates in days
     * Returns: INTEGER (number of days)
     * 
     * Usage: DB::raw(DatabaseCompatibilityService::dateDiffInDays('start_date', 'end_date'))
     */
    public static function dateDiffInDays(string $startDate, string $endDate): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST(julianday($endDate) - julianday($startDate) AS INTEGER)",
            'pgsql' => "EXTRACT(DAY FROM ($endDate - $startDate))::INTEGER",
            'mysql', 'mariadb' => "DATEDIFF($endDate, $startDate)",
            default => "EXTRACT(DAY FROM ($endDate - $startDate))::INTEGER",
        };
    }

    /**
     * Calculate difference between two timestamps in seconds
     * Returns: INTEGER (number of seconds)
     */
    public static function dateDiffInSeconds(string $startTs, string $endTs): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST((julianday($endTs) - julianday($startTs)) * 86400 AS INTEGER)",
            'pgsql' => "EXTRACT(EPOCH FROM ($endTs - $startTs))::INTEGER",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, $startTs, $endTs)",
            default => "EXTRACT(EPOCH FROM ($endTs - $startTs))::INTEGER",
        };
    }

    /**
     * Calculate difference between two timestamps in minutes
     * Returns: INTEGER (number of minutes)
     */
    public static function dateDiffInMinutes(string $startTs, string $endTs): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST((julianday($endTs) - julianday($startTs)) * 1440 AS INTEGER)",
            'pgsql' => "EXTRACT(EPOCH FROM ($endTs - $startTs))::INTEGER / 60",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(MINUTE, $startTs, $endTs)",
            default => "EXTRACT(EPOCH FROM ($endTs - $startTs))::INTEGER / 60",
        };
    }

    /**
     * Calculate difference between two timestamps in hours
     * Returns: INTEGER (number of hours)
     */
    public static function dateDiffInHours(string $startTs, string $endTs): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST((julianday($endTs) - julianday($startTs)) * 24 AS INTEGER)",
            'pgsql' => "EXTRACT(EPOCH FROM ($endTs - $startTs))::INTEGER / 3600",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(HOUR, $startTs, $endTs)",
            default => "EXTRACT(EPOCH FROM ($endTs - $startTs))::INTEGER / 3600",
        };
    }

    /**
     * Add days to a date/timestamp
     * Returns: TIMESTAMP
     */
    public static function addDays(string $column, int $days): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "DATETIME($column, '+$days days')",
            'pgsql' => "($column + INTERVAL '$days days')",
            'mysql', 'mariadb' => "DATE_ADD($column, INTERVAL $days DAY)",
            default => "($column + INTERVAL '$days days')",
        };
    }

    /**
     * Add hours to a timestamp
     * Returns: TIMESTAMP
     */
    public static function addHours(string $column, int $hours): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "DATETIME($column, '+$hours hours')",
            'pgsql' => "($column + INTERVAL '$hours hours')",
            'mysql', 'mariadb' => "DATE_ADD($column, INTERVAL $hours HOUR)",
            default => "($column + INTERVAL '$hours hours')",
        };
    }

    /**
     * Subtract days from a date/timestamp
     * Returns: TIMESTAMP
     */
    public static function subtractDays(string $column, int $days): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "DATETIME($column, '-$days days')",
            'pgsql' => "($column - INTERVAL '$days days')",
            'mysql', 'mariadb' => "DATE_SUB($column, INTERVAL $days DAY)",
            default => "($column - INTERVAL '$days days')",
        };
    }

    /**
     * Truncate time from a timestamp (returns just the date part)
     * Returns: DATE
     */
    public static function truncateTime(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "DATE($column)",
            'pgsql' => "DATE_TRUNC('day', $column)",
            'mysql', 'mariadb' => "DATE($column)",
            default => "DATE_TRUNC('day', $column)",
        };
    }

    /**
     * Cast string to integer
     * Returns: INTEGER
     */
    public static function castToInt(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST($column AS INTEGER)",
            'pgsql' => "CAST($column AS INTEGER)",
            'mysql', 'mariadb' => "CAST($column AS SIGNED)",
            default => "CAST($column AS INTEGER)",
        };
    }

    /**
     * Cast to string/varchar
     * Returns: VARCHAR/TEXT
     */
    public static function castToString(string $column): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST($column AS TEXT)",
            'pgsql' => "CAST($column AS VARCHAR)",
            'mysql', 'mariadb' => "CAST($column AS CHAR)",
            default => "CAST($column AS VARCHAR)",
        };
    }

    /**
     * Cast to decimal/numeric
     * Returns: DECIMAL(10,2)
     */
    public static function castToDecimal(string $column, int $precision = 10, int $scale = 2): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CAST($column AS REAL)",
            'pgsql' => "CAST($column AS NUMERIC($precision, $scale))",
            'mysql', 'mariadb' => "CAST($column AS DECIMAL($precision, $scale))",
            default => "CAST($column AS NUMERIC($precision, $scale))",
        };
    }

    /**
     * Check if string contains substring (case-insensitive)
     * Returns: BOOLEAN (works in WHERE clause)
     * 
     * Usage: ->where(DB::raw(DatabaseCompatibilityService::containsString('email', 'gmail')), 'like', '%'))
     *        or ->where('email', 'like', DB::raw(DatabaseCompatibilityService::likePattern('gmail')))
     */
    public static function containsString(string $column, string $needle): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "LOWER($column) LIKE LOWER('%$needle%')",
            'pgsql' => "LOWER($column) LIKE LOWER('%$needle%')",
            'mysql', 'mariadb' => "LOWER($column) LIKE LOWER('%$needle%')",
            default => "LOWER($column) LIKE LOWER('%$needle%')",
        };
    }

    /**
     * JSON extraction - get value by key
     * 
     * Usage: ->select(DB::raw(DatabaseCompatibilityService::jsonExtract('metadata', 'error_type') . ' as error_type'))
     */
    public static function jsonExtract(string $column, string $key): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "JSON_EXTRACT($column, '$.$key')",
            'pgsql' => "($column->>'$key')",
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT($column, '$.$key'))",
            default => "($column->>'$key')",
        };
    }

    /**
     * JSON extraction and cast to integer
     */
    public static function jsonExtractInt(string $column, string $key): string
    {
        $driver = self::getDriver();
        $extract = self::jsonExtract($column, $key);

        return match ($driver) {
            'sqlite' => "CAST($extract AS INTEGER)",
            'pgsql' => "CAST($extract AS INTEGER)",
            'mysql', 'mariadb' => "CAST($extract AS SIGNED)",
            default => "CAST($extract AS INTEGER)",
        };
    }

    /**
     * Coalesce - return first non-null value
     * This works the same across all databases
     * 
     * Usage: DB::raw(DatabaseCompatibilityService::coalesce(['column1', 'column2', "'default_value'"]))
     */
    public static function coalesce(array $columns): string
    {
        $cols = implode(', ', $columns);
        return "COALESCE($cols)";
    }

    /**
     * Get current timestamp in database native format
     * Returns: TIMESTAMP
     */
    public static function now(): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "CURRENT_TIMESTAMP",
            'pgsql' => "NOW()",
            'mysql', 'mariadb' => "NOW()",
            default => "NOW()",
        };
    }

    /**
     * Get current date in database native format
     * Returns: DATE
     */
    public static function today(): string
    {
        $driver = self::getDriver();

        return match ($driver) {
            'sqlite' => "DATE('now')",
            'pgsql' => "CURRENT_DATE",
            'mysql', 'mariadb' => "CURDATE()",
            default => "CURRENT_DATE",
        };
    }
}
