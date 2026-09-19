<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobLockService
{
    private const LOCK_NAMESPACE = 0x464B; // "FK"

    private static function keyFor(string $job): int
    {
        $hash = 0x811c9dc5;

        for ($i = 0; $i < strlen($job); $i++) {
            $hash ^= ord($job[$i]);
            $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
        }

        // Signed int4 like Postgres expects.
        if ($hash >= 0x80000000) {
            $hash -= 0x100000000;
        }

        return (int) $hash;
    }

    /**
     * Runs $task if no other runner holds the advisory lock for $job.
     * Returns ['ran' => true, 'result' => ...] or ['ran' => false, 'result' => null].
     */
    public function withJobLock(string $job, callable $task): array
    {
        $key = self::keyFor($job);
        $pdo = DB::connection()->getPdo();

        $stmt = $pdo->prepare('SELECT pg_try_advisory_lock(?, ?) AS acquired');
        $stmt->execute([self::LOCK_NAMESPACE, $key]);
        $acquired = $stmt->fetchColumn();

        if (!$acquired) {
            Log::info("[lock] {$job} is already running elsewhere — skipping");
            return ['ran' => false, 'result' => null];
        }

        try {
            return ['ran' => true, 'result' => $task()];
        } finally {
            $pdo->prepare('SELECT pg_advisory_unlock(?, ?)')->execute([self::LOCK_NAMESPACE, $key]);
        }
    }
}
