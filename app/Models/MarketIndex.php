<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketIndex extends Model
{
    protected $table = 'market_indices';
    protected $primaryKey = 'name';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'name', 'symbol', 'color', 'display_pattern', 'unit',
        'source', 'source_symbol', 'decimals', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function quotes()
    {
        return $this->hasMany(IndexQuote::class, 'index_name', 'name');
    }
}
