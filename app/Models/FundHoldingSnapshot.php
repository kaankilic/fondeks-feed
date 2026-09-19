<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundHoldingSnapshot extends Model
{
    protected $table = 'fund_holding_snapshots';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['fund_code', 'period', 'ticker', 'weight', 'source'];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'ingested_at' => 'datetime',
        ];
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_code', 'code');
    }
}
