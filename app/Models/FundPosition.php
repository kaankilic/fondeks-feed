<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundPosition extends Model
{
    protected $table = 'fund_positions';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'fund_code', 'ticker', 'period', 'direction', 'weight', 'change_points', 'rank',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'change_points' => 'float',
            'rank' => 'integer',
        ];
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_code', 'code');
    }

    public function symbol()
    {
        return $this->belongsTo(Symbol::class, 'ticker', 'ticker');
    }
}
