<?php

namespace App\Services\Ingest;

use App\Models\IngestRun;
use Illuminate\Support\Facades\Log;

class IngestRunService
{
    public function withRun(string $job, array $params, callable $task): array
    {
        $startedAt = now();

        $run = IngestRun::create([
            'job' => $job,
            'status' => 'running',
            'params' => $params,
            'started_at' => $startedAt,
        ]);

        try {
            $result = $task();

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'rows_read' => $result['rowsRead'] ?? 0,
                'rows_written' => $result['rowsWritten'] ?? 0,
            ]);

            Log::info("[ingest] {$job}: read={$result['rowsRead']}, wrote={$result['rowsWritten']}, duration=" . $startedAt->diffInMilliseconds(now()) . 'ms');

            return array_merge($result, [
                'run' => [
                    'id' => $run->id,
                    'job' => $job,
                    'rowsRead' => $result['rowsRead'] ?? 0,
                    'rowsWritten' => $result['rowsWritten'] ?? 0,
                    'durationMs' => $startedAt->diffInMilliseconds(now()),
                ],
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            Log::error("[ingest] {$job} failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
