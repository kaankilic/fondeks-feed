<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\DB;

/**
 * Postgres INSERT ... ON CONFLICT with a caller-supplied SET clause, so the
 * coalesce(excluded.x, table.x) semantics the ingest relies on survive — which
 * Laravel's built-in upsert() cannot express. Chunked to stay under the 65535
 * bound-parameter cap.
 */
class Upsert
{
    public const CHUNK_SIZE = 500;

    /**
     * @param string $table
     * @param array<int, array<string, mixed>> $rows  each row keyed by column
     * @param string[] $conflict  conflict target columns
     * @param string $setClause   e.g. "name = excluded.name, updated_at = now()"
     * @return int total affected rows
     */
    public static function run(string $table, array $rows, array $conflict, string $setClause, int $chunkSize = self::CHUNK_SIZE): int
    {
        if (empty($rows)) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $quotedCols = implode(', ', array_map(fn ($c) => "\"{$c}\"", $columns));
        $conflictCols = implode(', ', array_map(fn ($c) => "\"{$c}\"", $conflict));

        $written = 0;

        foreach (array_chunk($rows, $chunkSize) as $batch) {
            $placeholders = [];
            $bindings = [];

            foreach ($batch as $row) {
                $rowMarks = [];
                foreach ($columns as $col) {
                    $rowMarks[] = '?';
                    $bindings[] = $row[$col] ?? null;
                }
                $placeholders[] = '(' . implode(', ', $rowMarks) . ')';
            }

            $sql = "INSERT INTO \"{$table}\" ({$quotedCols}) VALUES "
                . implode(', ', $placeholders)
                . " ON CONFLICT ({$conflictCols}) DO UPDATE SET {$setClause}";

            $written += DB::affectingStatement($sql, $bindings);
        }

        return $written;
    }

    /** Plain insert without conflict handling, chunked. */
    public static function insert(string $table, array $rows, int $chunkSize = self::CHUNK_SIZE): int
    {
        $written = 0;
        foreach (array_chunk($rows, $chunkSize) as $batch) {
            DB::table($table)->insert($batch);
            $written += count($batch);
        }
        return $written;
    }
}
