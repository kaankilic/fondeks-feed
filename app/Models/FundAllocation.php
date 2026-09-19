<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundAllocation extends Model
{
    protected $table = 'fund_allocations';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['fund_code', 'date', 'label', 'pct', 'position'];

    protected function casts(): array
    {
        return [
            'pct' => 'float',
            'position' => 'integer',
        ];
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_code', 'code');
    }
}
