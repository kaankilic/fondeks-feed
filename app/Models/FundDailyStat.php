<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundDailyStat extends Model
{
    protected $table = 'fund_daily_stats';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'fund_code', 'date', 'price', 'total_value', 'investor_count', 'share_count',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'total_value' => 'float',
            'investor_count' => 'integer',
            'share_count' => 'float',
            'ingested_at' => 'datetime',
        ];
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_code', 'code');
    }
}
