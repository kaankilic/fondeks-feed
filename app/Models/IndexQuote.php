<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexQuote extends Model
{
    protected $table = 'index_quotes';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['index_name', 'date', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'ingested_at' => 'datetime',
        ];
    }

    public function marketIndex()
    {
        return $this->belongsTo(MarketIndex::class, 'index_name', 'name');
    }
}
