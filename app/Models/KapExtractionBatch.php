<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KapExtractionBatch extends Model
{
    protected $table = 'kap_extraction_batches';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'period', 'status', 'request_count', 'collected_at'];

    protected function casts(): array
    {
        return [
            'request_count' => 'integer',
            'collected_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function reports()
    {
        return $this->hasMany(KapPortfolioReport::class, 'batch_id', 'id');
    }
}
