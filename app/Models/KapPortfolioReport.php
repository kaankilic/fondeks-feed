<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KapPortfolioReport extends Model
{
    protected $table = 'kap_portfolio_reports';
    protected $primaryKey = 'disclosure_index';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'disclosure_index', 'fund_code', 'fund_title', 'period', 'published_at',
        'is_late', 'status', 'document_obj_id', 'document_name', 'document_url',
        'batch_id', 'holdings_count', 'note',
    ];

    protected function casts(): array
    {
        return [
            'disclosure_index' => 'integer',
            'published_at' => 'datetime',
            'is_late' => 'boolean',
            'holdings_count' => 'integer',
            'discovered_at' => 'datetime',
            'extracted_at' => 'datetime',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(KapExtractionBatch::class, 'batch_id', 'id');
    }
}
