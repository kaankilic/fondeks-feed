<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IngestRun extends Model
{
    use HasUuids;

    protected $table = 'ingest_runs';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'job', 'status', 'params', 'started_at', 'finished_at',
        'rows_written', 'rows_read', 'error',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'json',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rows_written' => 'integer',
            'rows_read' => 'integer',
        ];
    }
}
