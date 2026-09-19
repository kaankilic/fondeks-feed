<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundSimilarity extends Model
{
    protected $table = 'fund_similarities';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['fund_code', 'peer_code', 'peer_label', 'similarity'];

    protected function casts(): array
    {
        return [
            'similarity' => 'integer',
        ];
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_code', 'code');
    }

    public function peer()
    {
        return $this->belongsTo(Fund::class, 'peer_code', 'code');
    }
}
