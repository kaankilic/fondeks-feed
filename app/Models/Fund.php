<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    protected $table = 'funds';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'founder', 'category', 'isin', 'inception_date',
        'management_fee', 'withholding_tax', 'risk', 'buy_value_days',
        'sell_value_days', 'fund_type', 'on_tefas', 'tefas_type_code',
        'is_active', 'source',
    ];

    protected function casts(): array
    {
        return [
            'management_fee' => 'float',
            'withholding_tax' => 'float',
            'risk' => 'integer',
            'buy_value_days' => 'integer',
            'sell_value_days' => 'integer',
            'on_tefas' => 'boolean',
            'is_active' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }

    public function founderRelation()
    {
        return $this->belongsTo(Founder::class, 'founder', 'name');
    }

    public function dailyStats()
    {
        return $this->hasMany(FundDailyStat::class, 'fund_code', 'code');
    }

    public function positions()
    {
        return $this->hasMany(FundPosition::class, 'fund_code', 'code');
    }

    public function allocations()
    {
        return $this->hasMany(FundAllocation::class, 'fund_code', 'code');
    }

    public function similarities()
    {
        return $this->hasMany(FundSimilarity::class, 'fund_code', 'code');
    }

    public function holdingSnapshots()
    {
        return $this->hasMany(FundHoldingSnapshot::class, 'fund_code', 'code');
    }

    public function disclosures()
    {
        return $this->hasMany(FundDisclosure::class, 'fund_code', 'code');
    }
}
